<?php
/**
 * Create AW Chat v8 - Orchestrated Flow
 * Simpler n8n workflow that receives pre-built context from IRM
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

// n8n API settings
$n8nUrl = 'https://n8n.abroadworks.com';
$n8nApiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkN2ZmZTllYy02YjIzLTRmNzktODFmMS1kOTEyMDk0MmQ1YjMiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzY2OTYyNTIwfQ.kUkqVscN2_-BRJ3wCJVWLRADbEpZGEfmkpyT0c3kRCc';

echo "Creating AW Chat v8 (Orchestrated Flow)...\n";

// Delete old v7 workflow
$ch = curl_init("$n8nUrl/api/v1/workflows");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-N8N-API-KEY: ' . $n8nApiKey,
        'Content-Type: application/json'
    ]
]);
$response = curl_exec($ch);
$workflows = json_decode($response, true);

if ($workflows && isset($workflows['data'])) {
    foreach ($workflows['data'] as $wf) {
        if (strpos($wf['name'], 'AW Chat') !== false) {
            echo "Deleting: {$wf['name']}\n";
            $ch2 = curl_init("$n8nUrl/api/v1/workflows/{$wf['id']}");
            curl_setopt_array($ch2, [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: ' . $n8nApiKey]
            ]);
            curl_exec($ch2);
            curl_close($ch2);
        }
    }
}
curl_close($ch);

// Build v8 workflow
$workflow = [
    'name' => 'AW Chat v8 - Orchestrated',
    'nodes' => [
        // Webhook trigger
        [
            'id' => 'webhook',
            'name' => 'Webhook',
            'type' => 'n8n-nodes-base.webhook',
            'position' => [250, 300],
            'webhookId' => 'aw-chat-v8',
            'parameters' => [
                'path' => 'aw-chat-v8',
                'httpMethod' => 'POST',
                'responseMode' => 'responseNode',
                'options' => []
            ],
            'typeVersion' => 2
        ],

        // Extract input from orchestrator
        [
            'id' => 'extract',
            'name' => 'Extract Input',
            'type' => 'n8n-nodes-base.set',
            'position' => [450, 300],
            'parameters' => [
                'mode' => 'manual',
                'duplicateItem' => false,
                'assignments' => [
                    'assignments' => [
                        ['id' => 'session_id', 'name' => 'session_id', 'type' => 'string', 'value' => '={{ $json.body.session_id }}'],
                        ['id' => 'message', 'name' => 'message', 'type' => 'string', 'value' => '={{ $json.body.message }}'],
                        ['id' => 'context', 'name' => 'context', 'type' => 'object', 'value' => '={{ $json.body.context }}'],
                        ['id' => 'prompt', 'name' => 'prompt', 'type' => 'string', 'value' => '={{ $json.body.prompt }}']
                    ]
                ],
                'options' => []
            ],
            'typeVersion' => 3.4
        ],

        // Build system prompt
        [
            'id' => 'system_prompt',
            'name' => 'Build System Prompt',
            'type' => 'n8n-nodes-base.set',
            'position' => [650, 300],
            'parameters' => [
                'mode' => 'manual',
                'duplicateItem' => false,
                'assignments' => [
                    'assignments' => [
                        [
                            'id' => 'system_message',
                            'name' => 'system_message',
                            'type' => 'string',
                            'value' => <<<'PROMPT'
You are Ava, a helpful assistant for AbroadWorks, a company that provides virtual assistant services, graphic design, staffing solutions, and recruitment services.

YOUR CORE RULES:
1. NEVER ask for information that is already provided in the context
2. Ask for ONLY ONE piece of information per message
3. Keep responses SHORT (2-4 sentences maximum)
4. Match the user's language (if they write in Turkish, respond in Turkish)
5. Be professional but friendly
6. Follow the instructions provided in the context EXACTLY

SERVICES WE OFFER:
- Virtual Assistants: Administrative support, scheduling, email management, data entry ($8-15/hr)
- Graphic Design: Logo design, branding, marketing materials, social media graphics
- Staffing Solutions: Temporary and permanent placement for various roles
- Recruitment: End-to-end hiring process management

ABOUT ABROADWORKS:
- Based in the US, serving clients globally
- Team of 50+ professionals
- Operating since 2018
- Focus on quality and reliability

{{ $json.prompt }}
PROMPT
                        ]
                    ]
                ],
                'options' => []
            ],
            'typeVersion' => 3.4
        ],

        // OpenAI Chat - using lmChatOpenAi with proper message format
        [
            'id' => 'openai',
            'name' => 'OpenAI Chat',
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'position' => [850, 300],
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    'values' => [
                        [
                            'role' => 'system',
                            'content' => '={{ $json.system_message }}'
                        ],
                        [
                            'role' => 'user',
                            'content' => '={{ $("Extract Input").first().json.message }}'
                        ]
                    ]
                ],
                'options' => [
                    'temperature' => 0.7,
                    'maxTokens' => 300
                ]
            ],
            'typeVersion' => 1.3,
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // Extract data from response
        [
            'id' => 'extract_data',
            'name' => 'Extract Data',
            'type' => 'n8n-nodes-base.code',
            'position' => [1050, 300],
            'parameters' => [
                'jsCode' => <<<'CODE'
const response = $input.first().json.text || $input.first().json.message?.content || '';
const message = $('Extract Input').first().json.message;
const context = $('Extract Input').first().json.context;

// Extract data from user message
const extractedData = {};

// Name patterns
const namePatterns = [
  /my name is ([A-Za-z\s]+)/i,
  /i(?:'m| am) ([A-Za-z]+(?:\s[A-Za-z]+)?)/i,
  /call me ([A-Za-z]+)/i,
  /this is ([A-Za-z]+(?:\s[A-Za-z]+)?)/i,
  /benim adım ([A-Za-zğüşöçıİĞÜŞÖÇ\s]+)/i,
  /adım ([A-Za-zğüşöçıİĞÜŞÖÇ]+)/i
];

for (const pattern of namePatterns) {
  const match = message.match(pattern);
  if (match && !context?.user?.collected?.full_name) {
    extractedData.full_name = match[1].trim();
    break;
  }
}

// Email patterns
const emailPattern = /([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/;
const emailMatch = message.match(emailPattern);
if (emailMatch && !context?.user?.collected?.email) {
  extractedData.email = emailMatch[1];
}

// Phone patterns
const phonePatterns = [
  /(\+?[0-9]{1,4}[-.\s]?)?(\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}/,
  /0[0-9]{10}/,
  /\+90[0-9]{10}/
];

for (const pattern of phonePatterns) {
  const match = message.match(pattern);
  if (match && !context?.user?.collected?.phone) {
    extractedData.phone = match[0].replace(/[^0-9+]/g, '');
    break;
  }
}

// Company name - simple extraction after keywords
const companyPatterns = [
  /(?:company|business|work at|work for|firm|şirket|firma)\s+(?:is\s+)?(?:called\s+)?["']?([^"'\n,]+?)["']?(?:\s*[,.]|$)/i,
  /["']([^"']+)["']\s+(?:company|şirket|firma)/i
];

for (const pattern of companyPatterns) {
  const match = message.match(pattern);
  if (match && !context?.user?.collected?.business_name) {
    const name = match[1].trim();
    // Filter out obvious non-company responses
    if (name.length > 2 && !['yes', 'no', 'ok', 'evet', 'hayır'].includes(name.toLowerCase())) {
      extractedData.business_name = name;
    }
    break;
  }
}

// Time extraction for booking
const timePatterns = [
  /(\d{1,2})\s*(?::|\.)\s*(\d{2})?\s*(am|pm)?/i,
  /(\d{1,2})\s*(am|pm|AM|PM)/i,
  /at\s+(\d{1,2})/i,
  /saat\s+(\d{1,2})/i
];

let extractedTime = null;
for (const pattern of timePatterns) {
  const match = message.match(pattern);
  if (match) {
    extractedTime = match[0];
    break;
  }
}

// Date extraction
const datePatterns = [
  /tomorrow/i,
  /next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i,
  /(\d{1,2})[\/-](\d{1,2})[\/-]?(\d{2,4})?/,
  /yarın/i,
  /(pazartesi|salı|çarşamba|perşembe|cuma|cumartesi|pazar)/i
];

let extractedDate = null;
for (const pattern of datePatterns) {
  const match = message.match(pattern);
  if (match) {
    extractedDate = match[0];
    break;
  }
}

if (extractedTime) {
  extractedData.booking_time = extractedTime;
}
if (extractedDate) {
  extractedData.booking_date = extractedDate;
}

return [{
  json: {
    response: response,
    extracted_data: extractedData,
    has_extracted: Object.keys(extractedData).length > 0
  }
}];
CODE
            ],
            'typeVersion' => 2
        ],

        // Respond to webhook
        [
            'id' => 'respond',
            'name' => 'Respond',
            'type' => 'n8n-nodes-base.respondToWebhook',
            'position' => [1250, 300],
            'parameters' => [
                'options' => [
                    'responseCode' => 200,
                    'responseHeaders' => [
                        'entries' => [
                            ['name' => 'Content-Type', 'value' => 'application/json'],
                            ['name' => 'Access-Control-Allow-Origin', 'value' => '*']
                        ]
                    ]
                ],
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify({ response: $json.response, extracted_data: $json.extracted_data }) }}'
            ],
            'typeVersion' => 1.1
        ]
    ],

    'connections' => [
        'Webhook' => [
            'main' => [[['node' => 'Extract Input', 'type' => 'main', 'index' => 0]]]
        ],
        'Extract Input' => [
            'main' => [[['node' => 'Build System Prompt', 'type' => 'main', 'index' => 0]]]
        ],
        'Build System Prompt' => [
            'main' => [[['node' => 'OpenAI Chat', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI Chat' => [
            'main' => [[['node' => 'Extract Data', 'type' => 'main', 'index' => 0]]]
        ],
        'Extract Data' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],

    'settings' => [
        'executionOrder' => 'v1'
    ]
];

// Create workflow
$ch = curl_init("$n8nUrl/api/v1/workflows");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($workflow),
    CURLOPT_HTTPHEADER => [
        'X-N8N-API-KEY: ' . $n8nApiKey,
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && isset($result['id'])) {
    echo "Created: {$result['id']}\n";

    // Activate workflow
    $ch = curl_init("$n8nUrl/api/v1/workflows/{$result['id']}/activate");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: ' . $n8nApiKey]
    ]);
    curl_exec($ch);
    curl_close($ch);

    echo "Activated!\n";
    echo "Webhook: https://n8n.abroadworks.com/webhook/aw-chat-v8\n";
} else {
    echo "Error: $response\n";
}
