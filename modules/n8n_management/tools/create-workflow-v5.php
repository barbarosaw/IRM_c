<?php
/**
 * AW Chat v5 - Smart Memory & Context
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

$stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_key', 'n8n_chat_api_key')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$n8nHost = rtrim($settings['n8n_host'] ?? '', '/');
$n8nApiKey = $settings['n8n_api_key'] ?? '';
$chatApiKey = $settings['n8n_chat_api_key'] ?? '';

$irmBase = 'https://irm.abroadworks.com/modules/n8n_management/api/chat';

$systemPrompt = <<<'PROMPT'
You are AbroadWorks' friendly customer service assistant.

## About AbroadWorks
- Virtual Assistant services ($8-15/hr)
- Staffing solutions ($10-20/hr)
- Recruitment services (15-25% placement fee)
- Contact: info@abroadworks.com | Hours: Mon-Fri 10AM-6PM EST

## CRITICAL RULES
1. NEVER ask for information that's already in USER PROFILE
2. If info says "DO NOT ask again" - respect that completely
3. Only ask for ONE missing field per response
4. Be conversational, not robotic

## Your Response Format
Return ONLY valid JSON (no markdown, no explanation):
{
  "intent": "booking|services|job_application|general|off_topic",
  "extracted_data": {
    "name": "extracted or null",
    "email": "extracted or null",
    "phone": "extracted or null",
    "company": "extracted or null",
    "sector": "extracted or null",
    "employee_count": "extracted or null",
    "interest_area": "extracted or null"
  },
  "topics_discussed": ["topic1"],
  "user_interests": ["interest1"],
  "key_facts": ["important fact about user"],
  "response": "Your friendly response here"
}

## Guidelines
- Extract ANY contact info user provides naturally
- Acknowledge info they give: "Thanks [name]!"
- Keep responses under 80 words
- Match user's language
- For booking: offer to schedule consultation
- For job seekers: explain application process
PROMPT;

$workflow = [
    'name' => 'AW Chat v5 - Smart Memory',
    'nodes' => [
        // 1. Webhook
        [
            'parameters' => [
                'path' => 'aw-chat',
                'httpMethod' => 'POST',
                'responseMode' => 'responseNode',
                'options' => []
            ],
            'type' => 'n8n-nodes-base.webhook',
            'typeVersion' => 2,
            'position' => [0, 300],
            'id' => 'webhook',
            'name' => 'Webhook',
            'webhookId' => 'aw-chat'
        ],

        // 2. Prepare Data
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const input = $("Webhook").first().json.body;
return [{
  json: {
    visitor_id: input.visitor_id,
    session_id: input.session_id || null,
    page_url: input.page_url || "",
    page_title: input.page_title || "",
    message: input.message || ""
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [220, 300],
            'id' => 'prep',
            'name' => 'Prepare Data'
        ],

        // 3. Get/Create Session
        [
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBase/session.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ visitor_id: $json.visitor_id, page_url: $json.page_url, page_title: $json.page_title }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [440, 300],
            'id' => 'session',
            'name' => 'Get Session'
        ],

        // 4. Get Smart Context
        [
            'parameters' => [
                'method' => 'GET',
                'url' => "$irmBase/context.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendQuery' => true,
                'queryParameters' => [
                    'parameters' => [
                        ['name' => 'session_id', 'value' => '={{ $json.session_id }}']
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [660, 300],
            'id' => 'context',
            'name' => 'Get Context'
        ],

        // 5. Save User Message
        [
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBase/message.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Get Session").item.json.session_id, role: "user", content: $("Prepare Data").item.json.message }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [880, 300],
            'id' => 'save-user',
            'name' => 'Save User Msg'
        ],

        // 6. Build AI Request
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const session = $("Get Session").first().json;
const context = $("Get Context").first().json;
const userMsg = $("Prepare Data").first().json.message;

// Build the context-aware prompt
let contextBlock = "";
if (context.context_summary) {
  contextBlock = "=== CURRENT SESSION STATE ===\n" + context.context_summary + "\n=== END STATE ===\n\n";
}

const fullPrompt = contextBlock + "User says: " + userMsg;

return [{
  json: {
    session_id: session.session_id,
    user_message: userMsg,
    full_prompt: fullPrompt,
    existing_info: context.collected_info || {}
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 300],
            'id' => 'build',
            'name' => 'Build Request'
        ],

        // 7. OpenAI Chat
        [
            'parameters' => [
                'method' => 'POST',
                'url' => 'https://api.openai.com/v1/chat/completions',
                'authentication' => 'predefinedCredentialType',
                'nodeCredentialType' => 'openAiApi',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'Content-Type', 'value' => 'application/json']
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ model: "gpt-4o-mini", messages: [{ role: "system", content: ' . json_encode($systemPrompt) . ' }, { role: "user", content: $json.full_prompt }], max_tokens: 500, temperature: 0.7 }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1320, 300],
            'id' => 'openai',
            'name' => 'OpenAI',
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 8. Parse Response
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const session = $("Build Request").first().json;
const openai = $("OpenAI").first().json;
const existingInfo = session.existing_info || {};

let response = "Hello! How can I help you today?";
let intent = "general";
let extractedData = {};
let topicsDiscussed = [];
let userInterests = [];
let keyFacts = [];

try {
  let aiContent = openai.choices[0].message.content.trim();

  // Remove markdown code blocks if present
  aiContent = aiContent.replace(/^```json\s*/i, '').replace(/\s*```$/i, '');
  aiContent = aiContent.replace(/^```\s*/i, '').replace(/\s*```$/i, '');

  const parsed = JSON.parse(aiContent);

  response = parsed.response || response;
  intent = parsed.intent || intent;

  // Extract data - only non-null values
  if (parsed.extracted_data) {
    for (const [key, value] of Object.entries(parsed.extracted_data)) {
      if (value && value !== "null" && value !== null && value !== "") {
        extractedData[key] = value;
      }
    }
  }

  topicsDiscussed = parsed.topics_discussed || [];
  userInterests = parsed.user_interests || [];
  keyFacts = parsed.key_facts || [];

} catch (e) {
  // If parsing fails, use raw response
  if (openai.choices && openai.choices[0]) {
    response = openai.choices[0].message.content;
  }
}

return [{
  json: {
    session_id: session.session_id,
    response: response,
    intent: intent,
    extracted_data: extractedData,
    topics_discussed: topicsDiscussed,
    user_interests: userInterests,
    key_facts: keyFacts
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1540, 300],
            'id' => 'parse',
            'name' => 'Parse Response'
        ],

        // 9. Update Context
        [
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBase/context.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id, intent: $json.intent, collected_info: $json.extracted_data, topics_discussed: $json.topics_discussed, user_interests: $json.user_interests, key_facts: $json.key_facts }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1760, 300],
            'id' => 'update-ctx',
            'name' => 'Update Context'
        ],

        // 10. Save Bot Message
        [
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBase/message.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Parse Response").item.json.session_id, role: "assistant", content: $("Parse Response").item.json.response }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1980, 300],
            'id' => 'save-bot',
            'name' => 'Save Bot Msg'
        ],

        // 11. Respond
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify({ success: true, response: $("Parse Response").item.json.response, session_id: $("Parse Response").item.json.session_id, intent: $("Parse Response").item.json.intent, context_update: $("Update Context").item.json }) }}'
            ],
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [2200, 300],
            'id' => 'respond',
            'name' => 'Respond'
        ]
    ],
    'connections' => [
        'Webhook' => [
            'main' => [[['node' => 'Prepare Data', 'type' => 'main', 'index' => 0]]]
        ],
        'Prepare Data' => [
            'main' => [[['node' => 'Get Session', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Session' => [
            'main' => [[['node' => 'Get Context', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Context' => [
            'main' => [[['node' => 'Save User Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save User Msg' => [
            'main' => [[['node' => 'Build Request', 'type' => 'main', 'index' => 0]]]
        ],
        'Build Request' => [
            'main' => [[['node' => 'OpenAI', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI' => [
            'main' => [[['node' => 'Parse Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Parse Response' => [
            'main' => [[['node' => 'Update Context', 'type' => 'main', 'index' => 0]]]
        ],
        'Update Context' => [
            'main' => [[['node' => 'Save Bot Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save Bot Msg' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => ['executionOrder' => 'v1']
];

echo "Creating AW Chat v5 (Smart Memory)...\n";

// Delete old workflows
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$n8nHost/api/v1/workflows",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["X-N8N-API-KEY: $n8nApiKey"]
]);
$response = curl_exec($ch);
curl_close($ch);

$workflows = json_decode($response, true);
if (isset($workflows['data'])) {
    foreach ($workflows['data'] as $wf) {
        if (strpos($wf['name'], 'AW Chat') !== false) {
            echo "Deleting: {$wf['name']}\n";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "$n8nHost/api/v1/workflows/{$wf['id']}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => ["X-N8N-API-KEY: $n8nApiKey"]
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

// Create
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$n8nHost/api/v1/workflows",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["X-N8N-API-KEY: $n8nApiKey", 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($workflow)
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
    echo "Created: {$result['id']}\n";
    echo "Activating...\n";

    // Activate via API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$n8nHost/api/v1/workflows/{$result['id']}/activate",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["X-N8N-API-KEY: $n8nApiKey", 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => '{}'
    ]);
    $actResponse = curl_exec($ch);
    curl_close($ch);

    $actResult = json_decode($actResponse, true);
    if (isset($actResult['active']) && $actResult['active']) {
        echo "Activated!\n";
    } else {
        echo "Manual activation needed\n";
    }

    echo "\nWebhook: $n8nHost/webhook/aw-chat\n";
} else {
    echo "Error: $response\n";
}
