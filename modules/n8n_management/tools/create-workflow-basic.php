<?php
/**
 * Step 1: Basic Chat Flow
 * Webhook → Session → Save Message → AI Response → Save Response → Return
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

$stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_key', 'n8n_chat_api_key')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$n8nHost = rtrim($settings['n8n_host'] ?? '', '/');
$n8nApiKey = $settings['n8n_api_key'] ?? '';
$chatApiKey = $settings['n8n_chat_api_key'] ?? '';

if (!$n8nHost || !$n8nApiKey) {
    die("Error: n8n not configured\n");
}

$irmBase = 'https://irm.abroadworks.com/modules/n8n_management/api/chat';

$workflow = [
    'name' => 'AW Chat - Step 1 Basic',
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

        // 2. Get/Create Session
        [
            'parameters' => [
                'url' => "$irmBase/session.php",
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey],
                        ['name' => 'Content-Type', 'value' => 'application/json']
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ visitor_id: $json.visitor_id, page_url: $json.page_url, page_title: $json.page_title }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [220, 300],
            'id' => 'session',
            'name' => 'Get Session'
        ],

        // 3. Save User Message
        [
            'parameters' => [
                'url' => "$irmBase/message.php",
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey],
                        ['name' => 'Content-Type', 'value' => 'application/json']
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Get Session").item.json.session_id, role: "user", content: $("Webhook").item.json.message }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [440, 300],
            'id' => 'save-user',
            'name' => 'Save User Msg'
        ],

        // 4. AI Response (Simple)
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
                            'content' => '={{ $("Webhook").item.json.message }}'
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
            'position' => [660, 300],
            'id' => 'ai',
            'name' => 'AI Response',
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 5. Prepare Response
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const session = $("Get Session").first().json;
const ai = $("AI Response").first().json;

// Extract AI response text
let responseText = ai.message?.content || ai.text || ai.response || "Hello! How can I help you today?";

return [{
  json: {
    session_id: session.session_id,
    response: responseText
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [880, 300],
            'id' => 'prepare',
            'name' => 'Prepare Response'
        ],

        // 6. Save Bot Message
        [
            'parameters' => [
                'url' => "$irmBase/message.php",
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey],
                        ['name' => 'Content-Type', 'value' => 'application/json']
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id, role: "assistant", content: $json.response }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1100, 300],
            'id' => 'save-bot',
            'name' => 'Save Bot Msg'
        ],

        // 7. Respond to Widget
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify({ success: true, response: $("Prepare Response").item.json.response, session_id: $("Prepare Response").item.json.session_id }) }}'
            ],
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [1320, 300],
            'id' => 'respond',
            'name' => 'Respond'
        ]
    ],
    'connections' => [
        'Webhook' => [
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

echo "Creating Basic Flow...\n";

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
    echo "Webhook: $n8nHost/webhook/aw-chat\n\n";

    // Update settings
    $webhookUrl = "$n8nHost/webhook/aw-chat";
    $db->prepare("UPDATE n8n_chatbot_settings SET setting_value = ? WHERE setting_key = 'webhook_url'")->execute([$webhookUrl]);
    echo "✅ Settings updated\n";

    // Activate workflow
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
        echo "✅ Workflow activated!\n";
    } else {
        echo "⚠️ Please activate manually in n8n\n";
    }
} else {
    echo "❌ Error: $response\n";
}
