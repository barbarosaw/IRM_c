<?php
/**
 * AW Chat v6 - Smart Lead Collection with Memory
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
You are AbroadWorks' sales assistant. Your job is to help visitors AND collect their information for follow-up.

## ABOUT ABROADWORKS
- Virtual Assistants: $8-15/hr (admin, customer service, data entry)
- Graphic Designers: $12-20/hr (social media, branding, web design)
- Staffing Solutions: $10-25/hr (various roles)
- Recruitment: 15-25% placement fee
- Contact: info@abroadworks.com | Mon-Fri 10AM-6PM EST

## CRITICAL RULES
1. NEVER ask for information shown in "ALREADY KNOWN" section
2. When user provides info (name, email, phone, company), EXTRACT and CONFIRM it
3. Ask for ONE missing field naturally per response
4. Track what topics were discussed in qa_entry

## YOUR RESPONSE FORMAT
Return ONLY this JSON (no markdown, no explanation):
{
  "extracted": {
    "full_name": "name if user mentioned it, otherwise null",
    "email": "email if user mentioned it, otherwise null",
    "phone": "phone if user mentioned it, otherwise null",
    "business_name": "company if user mentioned it, otherwise null",
    "position_needed": "role they want to fill if mentioned, otherwise null",
    "location": "location if mentioned, otherwise null",
    "company_size": "size if mentioned (e.g., '10-50'), otherwise null",
    "industry": "industry if mentioned, otherwise null"
  },
  "primary_intent": "booking|services|pricing|job_application|general",
  "interests": ["virtual_assistant", "graphic_designer", "staffing", etc],
  "purchase_likelihood": "low|medium|high|very_high",
  "qa_entry": {"q": "what user asked about", "a": "brief answer given"},
  "response": "Your friendly response. If missing required info, naturally ask for ONE: name, email, phone, or company. Acknowledge any info they provided."
}

## EXAMPLES

User says: "Hi, I'm John from TechCorp"
Response should extract: full_name: "John", business_name: "TechCorp"
And ask for email or phone naturally.

User says: "My email is john@techcorp.com"
Response should extract: email: "john@techcorp.com"
And NOT ask for email again.

User says: "How much does a virtual assistant cost?"
Response should include qa_entry: {"q": "VA pricing", "a": "$8-15/hr"}
And interests: ["virtual_assistant"]
PROMPT;

$workflow = [
    'name' => 'AW Chat v6 - Lead Collector',
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
return [{
  json: {
    visitor_id: body.visitor_id,
    session_id: body.session_id || null,
    message: body.message || "",
    page_url: body.page_url || "",
    page_title: body.page_title || ""
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

        // 4. Get Context
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
            'id' => 'get-context',
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
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Get Session").item.json.session_id, role: "user", content: $("Extract Input").item.json.message }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [880, 300],
            'id' => 'save-user',
            'name' => 'Save User Msg'
        ],

        // 6. Build Prompt
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const session = $("Get Session").first().json;
const context = $("Get Context").first().json;
const userMsg = $("Extract Input").first().json.message;

// Build context-aware prompt
let contextBlock = context.context_for_ai || "NEW VISITOR - collect name, email, phone, company.";

const fullPrompt = `${contextBlock}

---
USER MESSAGE: ${userMsg}
---

Remember: Extract any info from the message. Do NOT ask for info already shown above.`;

return [{
  json: {
    session_id: session.session_id,
    visitor_id: $("Extract Input").first().json.visitor_id,
    user_message: userMsg,
    full_prompt: fullPrompt,
    current_context: context
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
                'jsonBody' => '={{ JSON.stringify({ model: "gpt-4o-mini", messages: [{ role: "system", content: ' . json_encode($systemPrompt) . ' }, { role: "user", content: $json.full_prompt }], max_tokens: 600, temperature: 0.7 }) }}'
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
const buildData = $("Build Prompt").first().json;
const openai = $("OpenAI").first().json;

let response = "Hello! How can I help you today?";
let extracted = {};
let primaryIntent = null;
let interests = [];
let purchaseLikelihood = null;
let qaEntry = null;

try {
  let content = openai.choices[0].message.content.trim();

  // Remove markdown code blocks
  content = content.replace(/^```json\s*/i, '').replace(/\s*```$/i, '');
  content = content.replace(/^```\s*/i, '').replace(/\s*```$/i, '');

  const parsed = JSON.parse(content);

  response = parsed.response || response;
  primaryIntent = parsed.primary_intent || null;
  interests = parsed.interests || [];
  purchaseLikelihood = parsed.purchase_likelihood || null;
  qaEntry = parsed.qa_entry || null;

  // Extract user data (filter nulls)
  if (parsed.extracted) {
    for (const [key, value] of Object.entries(parsed.extracted)) {
      if (value && value !== "null" && value !== null && value !== "") {
        extracted[key] = value;
      }
    }
  }
} catch (e) {
  // Fallback: use raw response
  if (openai.choices && openai.choices[0]) {
    response = openai.choices[0].message.content;
  }
}

return [{
  json: {
    session_id: buildData.session_id,
    visitor_id: buildData.visitor_id,
    response: response,
    extracted: extracted,
    primary_intent: primaryIntent,
    interests: interests,
    purchase_likelihood: purchaseLikelihood,
    qa_entry: qaEntry
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
                'jsonBody' => <<<'JS'
={{ JSON.stringify({
  session_id: $json.session_id,
  visitor_id: $json.visitor_id,
  full_name: $json.extracted.full_name || null,
  email: $json.extracted.email || null,
  phone: $json.extracted.phone || null,
  business_name: $json.extracted.business_name || null,
  position_needed: $json.extracted.position_needed || null,
  location: $json.extracted.location || null,
  company_size: $json.extracted.company_size || null,
  industry: $json.extracted.industry || null,
  primary_intent: $json.primary_intent,
  purchase_likelihood: $json.purchase_likelihood,
  interests: $json.interests,
  qa_entry: $json.qa_entry
}) }}
JS
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1760, 300],
            'id' => 'update-context',
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
                'responseBody' => <<<'JS'
={{ JSON.stringify({
  success: true,
  response: $("Parse Response").item.json.response,
  session_id: $("Parse Response").item.json.session_id,
  lead_update: $("Update Context").item.json
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
            'main' => [[['node' => 'Get Context', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Context' => [
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

echo "Creating AW Chat v6 (Lead Collector)...\n";

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

    // Activate
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
