<?php
/**
 * Update AW Chat v9b - Using OpenAI directly (no Agent nodes)
 * More reliable approach using direct chat completion
 */

$n8nUrl = 'https://n8n.abroadworks.com';
$n8nApiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkN2ZmZTllYy02YjIzLTRmNzktODFmMS1kOTEyMDk0MmQ1YjMiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzY2OTYyNTIwfQ.kUkqVscN2_-BRJ3wCJVWLRADbEpZGEfmkpyT0c3kRCc';
$workflowId = 'Q6GwrfZPyxknYoPc';
$irmBaseUrl = 'https://irm.abroadworks.com/modules/n8n_management/api/tools';

echo "Updating to AW Chat v9b - Direct OpenAI...\n";

// Deactivate first
$ch = curl_init("$n8nUrl/api/v1/workflows/$workflowId/deactivate");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: ' . $n8nApiKey]
]);
curl_exec($ch);
curl_close($ch);

// Simplified workflow using OpenAI HTTP API directly
$workflow = [
    'name' => 'AW Chat v9b - Direct OpenAI',
    'nodes' => [
        // 1. Webhook
        [
            'id' => 'webhook',
            'name' => 'Webhook',
            'type' => 'n8n-nodes-base.webhook',
            'position' => [100, 300],
            'webhookId' => 'aw-chat-v9',
            'parameters' => [
                'path' => 'aw-chat-v9',
                'httpMethod' => 'POST',
                'responseMode' => 'responseNode',
                'options' => []
            ],
            'typeVersion' => 2
        ],

        // 2. Extract Input
        [
            'id' => 'extract',
            'name' => 'Extract Input',
            'type' => 'n8n-nodes-base.set',
            'position' => [280, 300],
            'parameters' => [
                'mode' => 'manual',
                'duplicateItem' => false,
                'assignments' => [
                    'assignments' => [
                        ['id' => 'session_id', 'name' => 'session_id', 'type' => 'string', 'value' => '={{ $json.body.session_id }}'],
                        ['id' => 'message', 'name' => 'message', 'type' => 'string', 'value' => '={{ $json.body.message }}'],
                        ['id' => 'visitor_id', 'name' => 'visitor_id', 'type' => 'string', 'value' => '={{ $json.body.visitor_id }}']
                    ]
                ],
                'options' => []
            ],
            'typeVersion' => 3.4
        ],

        // 3. Get State
        [
            'id' => 'get_state',
            'name' => 'Get State',
            'type' => 'n8n-nodes-base.httpRequest',
            'position' => [460, 300],
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBaseUrl/get-state.php",
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id }) }}',
                'options' => []
            ],
            'typeVersion' => 4.2
        ],

        // 4. Build Combined Prompt (all 3 agents in one)
        [
            'id' => 'build_prompt',
            'name' => 'Build Prompt',
            'type' => 'n8n-nodes-base.code',
            'position' => [640, 300],
            'parameters' => [
                'jsCode' => <<<'CODE'
const message = $('Extract Input').first().json.message;
const sessionId = $('Extract Input').first().json.session_id;
const stateData = $('Get State').first().json;
const state = stateData.state || {};
const collected = state.collected || {};
const conversation = state.conversation || [];

// Build system message
const systemMessage = `You are Ava, a helpful assistant for AbroadWorks.

AbroadWorks offers:
- Virtual Assistant services ($8-15/hr) - admin support, scheduling, email management
- Graphic Design services - logos, marketing materials
- Staffing Solutions - temporary and permanent placement
- Recruitment Services - end-to-end hiring

WHAT WE ALREADY KNOW ABOUT THIS USER:
${JSON.stringify(collected, null, 2)}

RECENT CONVERSATION:
${conversation.slice(-4).join('\n')}

YOUR TASK:
1. First, analyze the user's message to extract any new information (name, email, phone, company, intent)
2. Then decide what goal to pursue (get name, get email, explain services, book call, etc.)
3. Finally, generate a natural response

RULES:
- Keep response SHORT (2-3 sentences max)
- Ask for ONLY ONE piece of information at a time
- NEVER ask for information we already have
- Match the user's language (Turkish gets Turkish response)
- Be friendly and helpful

Response format (JSON):
{
  "extracted": {
    "name": "name if mentioned, or null",
    "email": "email if mentioned, or null",
    "phone": "phone if mentioned, or null",
    "company": "company if mentioned, or null"
  },
  "intent": "services|booking|careers|support|general",
  "goal": "what you're trying to achieve this turn",
  "response": "your natural response to the user"
}`;

const userMessage = `User message: "${message}"

Analyze, decide, and respond.`;

return [{
  json: {
    session_id: sessionId,
    message: message,
    state: state,
    system_message: systemMessage,
    user_message: userMessage
  }
}];
CODE
            ],
            'typeVersion' => 2
        ],

        // 5. Call OpenAI (HTTP Request to OpenAI API)
        [
            'id' => 'openai_call',
            'name' => 'OpenAI Chat',
            'type' => 'n8n-nodes-base.httpRequest',
            'position' => [820, 300],
            'parameters' => [
                'method' => 'POST',
                'url' => 'https://api.openai.com/v1/chat/completions',
                'authentication' => 'predefinedCredentialType',
                'nodeCredentialType' => 'openAiApi',
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ model: "gpt-4o-mini", messages: [{ role: "system", content: $json.system_message }, { role: "user", content: $json.user_message }], temperature: 0.7, max_tokens: 500, response_format: { type: "json_object" } }) }}',
                'options' => []
            ],
            'typeVersion' => 4.2,
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 6. Parse Response and Save
        [
            'id' => 'parse_save',
            'name' => 'Parse and Save',
            'type' => 'n8n-nodes-base.code',
            'position' => [1000, 300],
            'parameters' => [
                'jsCode' => <<<'CODE'
const sessionId = $('Extract Input').first().json.session_id;
const openAiResponse = $input.first().json;

let aiOutput = {};
let response = "I'm sorry, I couldn't process that. How can I help you?";

try {
  const content = openAiResponse.choices?.[0]?.message?.content || '{}';
  aiOutput = JSON.parse(content);
  response = aiOutput.response || response;
} catch (e) {
  // Try to extract response from raw text
  const raw = openAiResponse.choices?.[0]?.message?.content || '';
  response = raw.length > 10 ? raw : response;
}

// Clean extracted data
const extracted = {};
if (aiOutput.extracted) {
  for (const [key, value] of Object.entries(aiOutput.extracted)) {
    if (value && value !== 'null' && value !== null && value !== '') {
      extracted[key] = value;
    }
  }
}

return [{
  json: {
    session_id: sessionId,
    response: response,
    extracted: extracted,
    intent: aiOutput.intent || 'general',
    goal: aiOutput.goal || 'engage'
  }
}];
CODE
            ],
            'typeVersion' => 2
        ],

        // 7. Save Extraction
        [
            'id' => 'save_extraction',
            'name' => 'Save Extraction',
            'type' => 'n8n-nodes-base.httpRequest',
            'position' => [1180, 300],
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBaseUrl/save-extraction.php",
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id, extracted: $json.extracted, intent: $json.intent }) }}',
                'options' => []
            ],
            'typeVersion' => 4.2
        ],

        // 8. Update Strategy
        [
            'id' => 'update_strategy',
            'name' => 'Update Strategy',
            'type' => 'n8n-nodes-base.httpRequest',
            'position' => [1360, 300],
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBaseUrl/update-strategy.php",
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Parse and Save").first().json.session_id, current_goal: $("Parse and Save").first().json.goal }) }}',
                'options' => []
            ],
            'typeVersion' => 4.2
        ],

        // 9. Respond
        [
            'id' => 'respond',
            'name' => 'Respond',
            'type' => 'n8n-nodes-base.respondToWebhook',
            'position' => [1540, 300],
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
                'responseBody' => '={{ JSON.stringify({ response: $("Parse and Save").first().json.response, debug: { intent: $("Parse and Save").first().json.intent, goal: $("Parse and Save").first().json.goal, extracted: $("Parse and Save").first().json.extracted } }) }}'
            ],
            'typeVersion' => 1.1
        ]
    ],

    'connections' => [
        'Webhook' => ['main' => [[['node' => 'Extract Input', 'type' => 'main', 'index' => 0]]]],
        'Extract Input' => ['main' => [[['node' => 'Get State', 'type' => 'main', 'index' => 0]]]],
        'Get State' => ['main' => [[['node' => 'Build Prompt', 'type' => 'main', 'index' => 0]]]],
        'Build Prompt' => ['main' => [[['node' => 'OpenAI Chat', 'type' => 'main', 'index' => 0]]]],
        'OpenAI Chat' => ['main' => [[['node' => 'Parse and Save', 'type' => 'main', 'index' => 0]]]],
        'Parse and Save' => ['main' => [[['node' => 'Save Extraction', 'type' => 'main', 'index' => 0]]]],
        'Save Extraction' => ['main' => [[['node' => 'Update Strategy', 'type' => 'main', 'index' => 0]]]],
        'Update Strategy' => ['main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]]
    ],

    'settings' => ['executionOrder' => 'v1']
];

// Update workflow
$ch = curl_init("$n8nUrl/api/v1/workflows/$workflowId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
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
    echo "Updated: {$result['id']}\n";

    // Activate
    $ch = curl_init("$n8nUrl/api/v1/workflows/{$result['id']}/activate");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: ' . $n8nApiKey]
    ]);
    curl_exec($ch);
    curl_close($ch);

    echo "Activated!\n";
    echo "Webhook: https://n8n.abroadworks.com/webhook/aw-chat-v9\n";
} else {
    echo "Error ($httpCode): $response\n";
}
