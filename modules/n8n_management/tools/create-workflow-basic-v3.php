<?php
/**
 * Step 1: Basic Chat Flow v3 - Using correct OpenAI node
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

$stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_key', 'n8n_chat_api_key')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$n8nHost = rtrim($settings['n8n_host'] ?? '', '/');
$n8nApiKey = $settings['n8n_api_key'] ?? '';
$chatApiKey = $settings['n8n_chat_api_key'] ?? '';

$irmBase = 'https://irm.abroadworks.com/modules/n8n_management/api/chat';

$systemPrompt = "You are AbroadWorks customer service assistant. Be helpful and concise (max 100 words).

AbroadWorks provides:
- Virtual Assistant services (\$8-15/hr)
- Staffing solutions (\$10-20/hr)
- Recruitment services (15-25% fee)

Contact: info@abroadworks.com
Hours: Mon-Fri 10AM-6PM EST";

$workflow = [
    'name' => 'AW Chat Basic v3',
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

        // 2. Prepare Session Data
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const input = $("Webhook").first().json.body;
return [{
  json: {
    visitor_id: input.visitor_id,
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
            'id' => 'prep-session',
            'name' => 'Prep Session'
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
                'jsonBody' => <<<'JS'
={
  "visitor_id": "{{ $json.visitor_id }}",
  "page_url": "{{ $json.page_url }}",
  "page_title": "{{ $json.page_title }}"
}
JS
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [440, 300],
            'id' => 'session',
            'name' => 'Get Session'
        ],

        // 4. Save User Message
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
                'jsonBody' => <<<'JS'
={
  "session_id": "{{ $json.session_id }}",
  "role": "user",
  "content": "{{ $("Prep Session").item.json.message }}"
}
JS
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [660, 300],
            'id' => 'save-user',
            'name' => 'Save User Msg'
        ],

        // 5. OpenAI Chat - Using HTTP Request to OpenAI API directly
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
                'jsonBody' => '={{ JSON.stringify({ model: "gpt-4o-mini", messages: [{ role: "system", content: ' . json_encode($systemPrompt) . ' }, { role: "user", content: $("Prep Session").item.json.message }], max_tokens: 200, temperature: 0.7 }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [880, 300],
            'id' => 'openai',
            'name' => 'OpenAI Chat',
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 6. Prepare Response
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const session = $("Get Session").first().json;
const openai = $("OpenAI Chat").first().json;
const msg = $("Prep Session").first().json;

// Extract response from OpenAI API response structure
let responseText = "Hello! How can I help you?";

if (openai.choices && openai.choices[0] && openai.choices[0].message) {
  responseText = openai.choices[0].message.content;
} else if (openai.error) {
  responseText = "I'm sorry, I encountered an error. Please try again.";
}

return [{
  json: {
    session_id: session.session_id,
    response: responseText,
    message: msg.message
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 300],
            'id' => 'prepare',
            'name' => 'Prepare Response'
        ],

        // 7. Save Bot Message
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
                'jsonBody' => <<<'JS'
={
  "session_id": "{{ $json.session_id }}",
  "role": "assistant",
  "content": "{{ $json.response }}"
}
JS
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1320, 300],
            'id' => 'save-bot',
            'name' => 'Save Bot Msg'
        ],

        // 8. Respond to Widget
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify({ success: true, response: $("Prepare Response").item.json.response, session_id: $("Prepare Response").item.json.session_id }) }}'
            ],
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [1540, 300],
            'id' => 'respond',
            'name' => 'Respond'
        ]
    ],
    'connections' => [
        'Webhook' => [
            'main' => [[['node' => 'Prep Session', 'type' => 'main', 'index' => 0]]]
        ],
        'Prep Session' => [
            'main' => [[['node' => 'Get Session', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Session' => [
            'main' => [[['node' => 'Save User Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save User Msg' => [
            'main' => [[['node' => 'OpenAI Chat', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI Chat' => [
            'main' => [[['node' => 'Prepare Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Prepare Response' => [
            'main' => [[['node' => 'Save Bot Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save Bot Msg' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => ['executionOrder' => 'v1']
];

echo "Creating Basic Flow v3 (Direct OpenAI API)...\n";

// Get current workflow ID to delete
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
            echo "Deleting old workflow: {$wf['name']} ({$wf['id']})\n";
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

// Create new
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
    echo "✅ Created: $workflowId\n";

    // Activate
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$n8nHost/api/v1/workflows/$workflowId/activate",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["X-N8N-API-KEY: $n8nApiKey"]
    ]);
    curl_exec($ch);
    curl_close($ch);
    echo "✅ Activated\n";
    echo "\nWebhook: $n8nHost/webhook/aw-chat\n";
} else {
    echo "❌ Error: $response\n";
}
