<?php
/**
 * AW Chat v7 - Brain-Powered Conversation
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
You are AbroadWorks' customer service assistant. Follow the RULES & INSTRUCTIONS provided below exactly.

## ABOUT ABROADWORKS
- Virtual Assistants: $8-15/hr (admin, customer service, data entry, scheduling)
- Graphic Designers: $12-20/hr (social media, branding, web design)
- Staffing Solutions: $10-25/hr (various dedicated roles)
- Recruitment: 15-25% placement fee
- Contact: info@abroadworks.com | Mon-Fri 10AM-6PM EST

## RESPONSE FORMAT
Return ONLY valid JSON:
{
  "detected_intent": "booking|virtual_assistant|graphic_design|staffing|pricing|services|confirm|off_topic|unknown",
  "extracted_data": {
    "full_name": "if mentioned",
    "email": "if mentioned",
    "phone": "if mentioned",
    "business_name": "if mentioned",
    "industry": "if mentioned",
    "company_size": "if mentioned",
    "position_needed": "if mentioned"
  },
  "booking_data": {
    "requested_date": "extracted date or null",
    "requested_time": "extracted time or null",
    "raw_time_input": "what user said about time"
  },
  "topics_discussed": {
    "virtual_assistant": {"mentioned": true/false, "questions": ["list"]},
    "graphic_design": {"mentioned": true/false},
    "staffing": {"mentioned": true/false}
  },
  "qa_entry": {"q": "topic asked", "a": "brief answer"},
  "response": "Your response following the RULES below"
}

## CRITICAL RULES
1. NEVER ask for info already shown in USER INFO
2. NEVER ask for time/date if already shown in BOOKING INFO
3. Follow the ACTIVE RULES & INSTRUCTIONS exactly
4. Keep responses under 80 words
5. Match user's language
PROMPT;

$workflow = [
    'name' => 'AW Chat v7 - Brain Powered',
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

        // 2. Extract Input
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const body = $("Webhook").first().json.body;

// Simple intent detection for brain
let intent = 'unknown';
const msg = (body.message || '').toLowerCase();

if (/book|call|meeting|schedule|appointment/.test(msg)) intent = 'booking';
else if (/virtual|assistant|va\b/.test(msg)) intent = 'virtual_assistant';
else if (/graphic|design|logo|brand/.test(msg)) intent = 'graphic_design';
else if (/staff|hire|recruit|employee/.test(msg)) intent = 'staffing';
else if (/price|cost|rate|how much/.test(msg)) intent = 'pricing';
else if (/service|offer|provide|help/.test(msg)) intent = 'services';
else if (/yes|ok|confirm|sure|great|perfect/.test(msg)) intent = 'confirm';
else if (/weather|sport|news|joke/.test(msg)) intent = 'off_topic';

return [{
  json: {
    visitor_id: body.visitor_id,
    session_id: body.session_id || null,
    message: body.message || "",
    detected_intent: intent,
    page_url: body.page_url || ""
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [220, 300],
            'id' => 'extract',
            'name' => 'Extract Input'
        ],

        // 3. Get Session
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
                'jsonBody' => '={{ JSON.stringify({ visitor_id: $json.visitor_id, page_url: $json.page_url }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [440, 300],
            'id' => 'session',
            'name' => 'Get Session'
        ],

        // 4. Get Brain Context
        [
            'parameters' => [
                'method' => 'GET',
                'url' => "$irmBase/brain.php",
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
                        ['name' => 'message', 'value' => '={{ $("Extract Input").item.json.message }}'],
                        ['name' => 'intent', 'value' => '={{ $("Extract Input").item.json.detected_intent }}']
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [660, 300],
            'id' => 'brain',
            'name' => 'Get Brain'
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
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Get Session").item.json.session_id, role: "user", content: $("Extract Input").item.json.message }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [880, 300],
            'id' => 'save-user',
            'name' => 'Save User Msg'
        ],

        // 6. Build AI Prompt
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const brain = $("Get Brain").first().json;
const userMsg = $("Extract Input").first().json.message;
const sessionId = $("Get Session").first().json.session_id;
const visitorId = $("Extract Input").first().json.visitor_id;

// Build prompt with context and rules
let prompt = "";

// Add context summary (user info, booking status, topics)
if (brain.context_for_ai) {
  prompt += brain.context_for_ai + "\n";
}

// Add AI instructions from rules engine
if (brain.ai_instructions) {
  prompt += brain.ai_instructions + "\n";
}

// Add current message
prompt += "---\nUSER MESSAGE: " + userMsg + "\n---";

return [{
  json: {
    session_id: sessionId,
    visitor_id: visitorId,
    user_message: userMsg,
    full_prompt: prompt,
    brain_state: brain.brain,
    triggered_rules: brain.triggered_rules || []
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 300],
            'id' => 'build-prompt',
            'name' => 'Build Prompt'
        ],

        // 7. OpenAI
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
                'jsonBody' => '={{ JSON.stringify({ model: "gpt-4o-mini", messages: [{ role: "system", content: ' . json_encode($systemPrompt) . ' }, { role: "user", content: $json.full_prompt }], max_tokens: 700, temperature: 0.7 }) }}'
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
const build = $("Build Prompt").first().json;
const openai = $("OpenAI").first().json;

let response = "Hello! How can I help you today?";
let extractedData = {};
let bookingData = {};
let topicsDiscussed = {};
let qaEntry = null;
let detectedIntent = 'unknown';

try {
  let content = openai.choices[0].message.content.trim();
  content = content.replace(/^```json\s*/i, '').replace(/\s*```$/i, '');

  const parsed = JSON.parse(content);

  response = parsed.response || response;
  detectedIntent = parsed.detected_intent || detectedIntent;

  // Extract lead data
  if (parsed.extracted_data) {
    for (const [k, v] of Object.entries(parsed.extracted_data)) {
      if (v && v !== "null" && v !== "if mentioned") {
        extractedData[k] = v;
      }
    }
  }

  // Extract booking data
  if (parsed.booking_data) {
    for (const [k, v] of Object.entries(parsed.booking_data)) {
      if (v && v !== "null" && v !== "extracted date or null") {
        bookingData[k] = v;
      }
    }
  }

  // Topics discussed
  if (parsed.topics_discussed) {
    topicsDiscussed = parsed.topics_discussed;
  }

  // QA entry
  if (parsed.qa_entry && parsed.qa_entry.q && parsed.qa_entry.a) {
    qaEntry = parsed.qa_entry;
  }

} catch (e) {
  if (openai.choices && openai.choices[0]) {
    response = openai.choices[0].message.content;
  }
}

return [{
  json: {
    session_id: build.session_id,
    visitor_id: build.visitor_id,
    response: response,
    detected_intent: detectedIntent,
    extracted_data: extractedData,
    booking_data: bookingData,
    topics_discussed: topicsDiscussed,
    qa_entry: qaEntry,
    brain_state: build.brain_state,
    triggered_rules: build.triggered_rules
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

        // 9. Update Brain
        [
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBase/brain.php",
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => <<<'JS'
={{ JSON.stringify({
  session_id: $json.session_id,
  visitor_id: $json.visitor_id,
  booking: $json.booking_data,
  topics: $json.topics_discussed,
  last_ai_action: $json.detected_intent,
  lead: {
    ...$json.extracted_data,
    qa_entry: $json.qa_entry
  }
}) }}
JS
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1760, 300],
            'id' => 'update-brain',
            'name' => 'Update Brain'
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
                'responseBody' => <<<'JS'
={{ JSON.stringify({
  success: true,
  response: $("Parse Response").item.json.response,
  session_id: $("Parse Response").item.json.session_id,
  brain: $("Parse Response").item.json.brain_state,
  triggered_rules: $("Parse Response").item.json.triggered_rules
}) }}
JS
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
            'main' => [[['node' => 'Extract Input', 'type' => 'main', 'index' => 0]]]
        ],
        'Extract Input' => [
            'main' => [[['node' => 'Get Session', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Session' => [
            'main' => [[['node' => 'Get Brain', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Brain' => [
            'main' => [[['node' => 'Save User Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save User Msg' => [
            'main' => [[['node' => 'Build Prompt', 'type' => 'main', 'index' => 0]]]
        ],
        'Build Prompt' => [
            'main' => [[['node' => 'OpenAI', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI' => [
            'main' => [[['node' => 'Parse Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Parse Response' => [
            'main' => [[['node' => 'Update Brain', 'type' => 'main', 'index' => 0]]]
        ],
        'Update Brain' => [
            'main' => [[['node' => 'Save Bot Msg', 'type' => 'main', 'index' => 0]]]
        ],
        'Save Bot Msg' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => ['executionOrder' => 'v1']
];

echo "Creating AW Chat v7 (Brain Powered)...\n";

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
    echo $actResult['active'] ? "Activated!\n" : "Needs manual activation\n";

    echo "\nWebhook: $n8nHost/webhook/aw-chat\n";
} else {
    echo "Error: $response\n";
}
