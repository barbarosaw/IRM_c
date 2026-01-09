<?php
/**
 * Step 1: Basic Chat Flow v2 - Fixed HTTP Request body
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

$stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_key', 'n8n_chat_api_key')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$n8nHost = rtrim($settings['n8n_host'] ?? '', '/');
$n8nApiKey = $settings['n8n_api_key'] ?? '';
$chatApiKey = $settings['n8n_chat_api_key'] ?? '';

$irmBase = 'https://irm.abroadworks.com/modules/n8n_management/api/chat';

$workflow = [
    'name' => 'AW Chat Basic v2',
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

        // 5. AI Response
        [
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    'values' => [
                        [
                            'role' => 'system',
                            'content' => "You are AbroadWorks customer service assistant. Be helpful and concise (max 100 words).\n\nAbroadWorks provides:\n- Virtual Assistant services (\$8-15/hr)\n- Staffing solutions (\$10-20/hr)\n- Recruitment services (15-25% fee)\n\nContact: info@abroadworks.com\nHours: Mon-Fri 10AM-6PM EST"
                        ],
                        [
                            'role' => 'user',
                            'content' => '={{ $("Prep Session").item.json.message }}'
                        ]
                    ]
                ],
                'options' => [
                    'temperature' => 0.7,
                    'maxTokens' => 200
                ]
            ],
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'typeVersion' => 1,
            'position' => [880, 300],
            'id' => 'ai',
            'name' => 'AI Response',
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
const ai = $("AI Response").first().json;
const msg = $("Prep Session").first().json;

let responseText = ai.message?.content || ai.text || "Hello! How can I help you?";

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
            'main' => [[['node' => 'AI Response', 'type' => 'main', 'index' => 0]]]
        ],
        'AI Response' => [
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

echo "Creating Basic Flow v2...\n";

// Delete old workflow
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$n8nHost/api/v1/workflows/EyE9KPGYzBKgEwAH",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => ["X-N8N-API-KEY: $n8nApiKey"]
]);
curl_exec($ch);
curl_close($ch);
echo "Old workflow deleted\n";

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
} else {
    echo "❌ Error: $response\n";
}
