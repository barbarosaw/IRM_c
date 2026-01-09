<?php
/**
 * AW Chat v4 - Intent Detection & Lead Collection
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
You are AbroadWorks' friendly customer service assistant. Your goal is to help visitors while politely gathering their contact information.

## About AbroadWorks
- Virtual Assistant services ($8-15/hr)
- Staffing solutions ($10-20/hr)
- Recruitment services (15-25% placement fee)
- Contact: info@abroadworks.com
- Hours: Mon-Fri 10AM-6PM EST

## Your Tasks
1. DETECT INTENT from user message (booking, services, job_application, general, off_topic)
2. EXTRACT any contact info the user provides naturally in conversation
3. RESPOND helpfully while gently asking for missing required info

## Data Collection Priority
Required (ask politely if missing):
- name: User's full name
- email: Email address
- phone: Phone number
- company: Company name

Optional (ask if relevant):
- sector: Industry/sector
- employee_count: Company size
- interest_area: Which service interests them

## Response Format
You MUST respond with valid JSON in this exact format:
```json
{
  "intent": "booking|services|job_application|general|off_topic",
  "extracted_data": {
    "name": "extracted name or null",
    "email": "extracted email or null",
    "phone": "extracted phone or null",
    "company": "extracted company or null",
    "sector": "extracted sector or null",
    "employee_count": "extracted count or null",
    "interest_area": "extracted interest or null"
  },
  "response": "Your friendly response here. If missing required info, politely ask for ONE missing field."
}
```

## Guidelines
- Be conversational and friendly, not robotic
- Ask for ONE missing required field at a time
- If user provides info, acknowledge it warmly
- For booking intent: mention you can help schedule a consultation
- For job applications: explain the application process
- For off-topic: gently redirect to AbroadWorks services
- Keep responses under 100 words
- Always respond in the user's language
PROMPT;

$workflow = [
    'name' => 'AW Chat v4 - Smart',
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

        // 4. Get History
        [
            'parameters' => [
                'method' => 'GET',
                'url' => "$irmBase/history.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendQuery' => true,
                'queryParameters' => [
                    'parameters' => [
                        ['name' => 'session_id', 'value' => '={{ $json.session_id }}'],
                        ['name' => 'limit', 'value' => '10']
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [660, 200],
            'id' => 'history',
            'name' => 'Get History'
        ],

        // 5. Get Lead Data
        [
            'parameters' => [
                'method' => 'GET',
                'url' => "$irmBase/leads.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendQuery' => true,
                'queryParameters' => [
                    'parameters' => [
                        ['name' => 'session_id', 'value' => '={{ $("Get Session").item.json.session_id }}']
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [660, 400],
            'id' => 'lead-data',
            'name' => 'Get Lead Data'
        ],

        // 6. Merge Context
        [
            'parameters' => [
                'mode' => 'combine',
                'combineBy' => 'combineAll',
                'options' => []
            ],
            'type' => 'n8n-nodes-base.merge',
            'typeVersion' => 3,
            'position' => [880, 300],
            'id' => 'merge',
            'name' => 'Merge Context'
        ],

        // 7. Save User Message
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
            'position' => [1100, 300],
            'id' => 'save-user',
            'name' => 'Save User Msg'
        ],

        // 8. Build AI Prompt
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<JS
const session = \$("Get Session").first().json;
const history = \$("Get History").first().json;
const leadData = \$("Get Lead Data").first().json;
const userMsg = \$("Prepare Data").first().json.message;

// Build conversation context
let conversationContext = "";
if (history.messages && history.messages.length > 0) {
  conversationContext = "Previous conversation:\\n" +
    history.messages.map(m => m.role + ": " + m.content).join("\\n") + "\\n\\n";
}

// Build collected data context
let collectedContext = "";
if (leadData.collected_info && Object.keys(leadData.collected_info).length > 0) {
  collectedContext = "Already collected info: " + JSON.stringify(leadData.collected_info) + "\\n\\n";
}

// Build the full prompt
const fullPrompt = conversationContext + collectedContext + "Current user message: " + userMsg;

return [{
  json: {
    session_id: session.session_id,
    user_message: userMsg,
    full_prompt: fullPrompt,
    collected_info: leadData.collected_info || {}
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1320, 300],
            'id' => 'build-prompt',
            'name' => 'Build AI Prompt'
        ],

        // 9. OpenAI Chat
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
            'position' => [1540, 300],
            'id' => 'openai',
            'name' => 'OpenAI Chat',
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 10. Parse AI Response
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const session = $("Build AI Prompt").first().json;
const openai = $("OpenAI Chat").first().json;
const existingData = session.collected_info || {};

let responseText = "Hello! How can I help you today?";
let intent = "general";
let extractedData = {};

try {
  const aiContent = openai.choices[0].message.content;

  // Try to parse JSON from response
  let jsonMatch = aiContent.match(/```json\s*([\s\S]*?)\s*```/);
  let parsed;

  if (jsonMatch) {
    parsed = JSON.parse(jsonMatch[1]);
  } else {
    // Try direct JSON parse
    parsed = JSON.parse(aiContent);
  }

  responseText = parsed.response || responseText;
  intent = parsed.intent || intent;
  extractedData = parsed.extracted_data || {};

  // Filter out null values
  extractedData = Object.fromEntries(
    Object.entries(extractedData).filter(([k, v]) => v !== null && v !== "null" && v !== "")
  );

} catch (e) {
  // If JSON parsing fails, use raw response
  if (openai.choices && openai.choices[0]) {
    responseText = openai.choices[0].message.content;
  }
}

// Merge new data with existing
const mergedData = { ...existingData, ...extractedData };

return [{
  json: {
    session_id: session.session_id,
    response: responseText,
    intent: intent,
    extracted_data: extractedData,
    merged_data: mergedData,
    user_message: session.user_message
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1760, 300],
            'id' => 'parse',
            'name' => 'Parse Response'
        ],

        // 11. Update Lead Data
        [
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBase/leads.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id, primary_intent: $json.intent, collected_info: $json.extracted_data }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1980, 300],
            'id' => 'update-lead',
            'name' => 'Update Lead'
        ],

        // 12. Save Bot Message
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
            'position' => [2200, 300],
            'id' => 'save-bot',
            'name' => 'Save Bot Msg'
        ],

        // 13. Respond
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify({ success: true, response: $("Parse Response").item.json.response, session_id: $("Parse Response").item.json.session_id, intent: $("Parse Response").item.json.intent, collected_data: $("Update Lead").item.json }) }}'
            ],
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [2420, 300],
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
            'main' => [[
                ['node' => 'Get History', 'type' => 'main', 'index' => 0],
                ['node' => 'Get Lead Data', 'type' => 'main', 'index' => 0]
            ]]
        ],
        'Get History' => [
            'main' => [[['node' => 'Merge Context', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Lead Data' => [
            'main' => [[['node' => 'Merge Context', 'type' => 'main', 'index' => 1]]]
        ],
        'Merge Context' => [
            'main' => [[['node' => 'Save User Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save User Msg' => [
            'main' => [[['node' => 'Build AI Prompt', 'type' => 'main', 'index' => 0]]]
        ],
        'Build AI Prompt' => [
            'main' => [[['node' => 'OpenAI Chat', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI Chat' => [
            'main' => [[['node' => 'Parse Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Parse Response' => [
            'main' => [[['node' => 'Update Lead', 'type' => 'main', 'index' => 0]]]
        ],
        'Update Lead' => [
            'main' => [[['node' => 'Save Bot Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save Bot Msg' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => ['executionOrder' => 'v1']
];

echo "Creating AW Chat v4 (Smart)...\n";

// Delete old AW Chat workflows
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
            echo "Deleting: {$wf['name']} ({$wf['id']})\n";
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

// Create new workflow
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
    $workflowId = $result['id'];
    echo "Created: $workflowId\n";

    // Activate
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$n8nHost/api/v1/workflows/$workflowId/activate",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["X-N8N-API-KEY: $n8nApiKey"]
    ]);
    $actResponse = curl_exec($ch);
    $actCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($actCode >= 200 && $actCode < 300) {
        echo "Activated!\n";
    } else {
        echo "Activation failed: $actResponse\n";
    }

    echo "\nWebhook: $n8nHost/webhook/aw-chat\n";
} else {
    echo "Error: $response\n";
}
