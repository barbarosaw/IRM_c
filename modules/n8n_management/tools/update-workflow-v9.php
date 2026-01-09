<?php
/**
 * Update AW Chat to v9 - Multi-Agent Flow
 * 3 Agents: Analyzer, Strategist, Responder
 * Uses existing workflow, updates via PATCH
 */

$n8nUrl = 'https://n8n.abroadworks.com';
$n8nApiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkN2ZmZTllYy02YjIzLTRmNzktODFmMS1kOTEyMDk0MmQ1YjMiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwiaWF0IjoxNzY2OTYyNTIwfQ.kUkqVscN2_-BRJ3wCJVWLRADbEpZGEfmkpyT0c3kRCc';
$workflowId = 'Q6GwrfZPyxknYoPc';
$irmBaseUrl = 'https://irm.abroadworks.com/modules/n8n_management/api/tools';

echo "Updating to AW Chat v9 - Multi-Agent Flow...\n";

// First deactivate the workflow
$ch = curl_init("$n8nUrl/api/v1/workflows/$workflowId/deactivate");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: ' . $n8nApiKey]
]);
curl_exec($ch);
curl_close($ch);
echo "Deactivated workflow\n";

// Build v9 workflow with 3 agents
$workflow = [
    'name' => 'AW Chat v9 - Multi-Agent',
    'nodes' => [
        // 1. Webhook trigger
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
            'id' => 'extract_input',
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

        // 3. Get Current State from IRM
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

        // 4. Prepare Analyzer Input
        [
            'id' => 'prep_analyzer',
            'name' => 'Prepare Analyzer',
            'type' => 'n8n-nodes-base.set',
            'position' => [640, 300],
            'parameters' => [
                'mode' => 'manual',
                'duplicateItem' => false,
                'assignments' => [
                    'assignments' => [
                        ['id' => 'chatInput', 'name' => 'chatInput', 'type' => 'string', 'value' => <<<'PROMPT'
Analyze this user message and extract any information.

USER MESSAGE: {{ $('Extract Input').first().json.message }}

CURRENT STATE:
- Already collected: {{ JSON.stringify($json.state?.collected || {}) }}
- Turn count: {{ $json.state?.turn_count || 0 }}
- Current goal: {{ $json.state?.current_goal || 'greet_and_identify' }}

Extract from the message:
1. Name (if user introduces themselves)
2. Email (if provided)
3. Phone (if provided)
4. Company name (if mentioned)
5. Intent (what does the user want? Options: services, booking, careers, support, general)
6. Sentiment (positive, neutral, negative)

Respond ONLY with a JSON object:
{
  "extracted": {
    "name": "extracted name or null",
    "email": "extracted email or null",
    "phone": "extracted phone or null",
    "company": "extracted company or null"
  },
  "intent": "detected intent",
  "confidence": 0.0 to 1.0,
  "sentiment": "positive/neutral/negative"
}
PROMPT
                        ]
                    ]
                ],
                'options' => []
            ],
            'typeVersion' => 3.4
        ],

        // 5. Agent 1: ANALYZER
        [
            'id' => 'agent_analyzer',
            'name' => 'Analyzer Agent',
            'type' => '@n8n/n8n-nodes-langchain.agent',
            'position' => [820, 300],
            'parameters' => [
                'promptType' => 'define',
                'text' => '={{ $json.chatInput }}',
                'options' => [
                    'systemMessage' => 'You are a data extraction AI. Extract information from user messages accurately. Always respond with valid JSON only, no other text.'
                ]
            ],
            'typeVersion' => 1.7
        ],

        // 5b. OpenAI Model for Analyzer
        [
            'id' => 'openai_analyzer',
            'name' => 'OpenAI Analyzer',
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'position' => [820, 500],
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'options' => [
                    'temperature' => 0.3
                ]
            ],
            'typeVersion' => 1,
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 6. Parse Analyzer Output
        [
            'id' => 'parse_analyzer',
            'name' => 'Parse Analyzer',
            'type' => 'n8n-nodes-base.code',
            'position' => [1000, 300],
            'parameters' => [
                'jsCode' => <<<'CODE'
const response = $input.first().json.output || $input.first().json.text || '';
let analysis = {};

try {
  // Try to extract JSON from the response
  const jsonMatch = response.match(/\{[\s\S]*\}/);
  if (jsonMatch) {
    analysis = JSON.parse(jsonMatch[0]);
  }
} catch (e) {
  analysis = { extracted: {}, intent: 'general', confidence: 0.5 };
}

// Get state from previous node
const state = $('Get State').first().json.state || {};
const sessionId = $('Extract Input').first().json.session_id;
const message = $('Extract Input').first().json.message;

// Clean extracted data - remove nulls
const extracted = {};
if (analysis.extracted) {
  for (const [key, value] of Object.entries(analysis.extracted)) {
    if (value && value !== 'null' && value !== null) {
      extracted[key] = value;
    }
  }
}

return [{
  json: {
    session_id: sessionId,
    message: message,
    state: state,
    analysis: {
      extracted: extracted,
      intent: analysis.intent || 'general',
      confidence: analysis.confidence || 0.5,
      sentiment: analysis.sentiment || 'neutral'
    }
  }
}];
CODE
            ],
            'typeVersion' => 2
        ],

        // 7. Save Extraction to IRM
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
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id, extracted: $json.analysis.extracted, intent: $json.analysis.intent, confidence: $json.analysis.confidence, sentiment: $json.analysis.sentiment }) }}',
                'options' => []
            ],
            'typeVersion' => 4.2
        ],

        // 8. Prepare Strategist Input
        [
            'id' => 'prep_strategist',
            'name' => 'Prepare Strategist',
            'type' => 'n8n-nodes-base.set',
            'position' => [1360, 300],
            'parameters' => [
                'mode' => 'manual',
                'duplicateItem' => false,
                'assignments' => [
                    'assignments' => [
                        ['id' => 'chatInput', 'name' => 'chatInput', 'type' => 'string', 'value' => <<<'PROMPT'
You are a conversation strategist for AbroadWorks, a company offering:
- Virtual Assistant services ($8-15/hr)
- Graphic Design services
- Staffing Solutions
- Recruitment Services

CURRENT SITUATION:
- User message: {{ $('Extract Input').first().json.message }}
- Detected intent: {{ $('Parse Analyzer').first().json.analysis.intent }}
- Confidence: {{ $('Parse Analyzer').first().json.analysis.confidence }}
- Sentiment: {{ $('Parse Analyzer').first().json.analysis.sentiment }}

DATA WE HAVE:
{{ JSON.stringify($('Save Extraction').first().json.collected || $('Parse Analyzer').first().json.state?.collected || {}) }}

CONVERSATION HISTORY:
- Turn: {{ $('Parse Analyzer').first().json.state?.turn_count || 0 }}
- Previous goal: {{ $('Parse Analyzer').first().json.state?.current_goal || 'greet_and_identify' }}
- Completed goals: {{ JSON.stringify($('Parse Analyzer').first().json.state?.goals_completed || []) }}

GOAL PRIORITY:
1. If we don't have name → get name
2. If we have name but no email → get email
3. If interest in services → explain and get phone for callback
4. If booking intent → collect phone, then offer calendar
5. If job seeker → politely redirect to careers page
6. Keep conversation engaging and natural

Decide:
1. What is the current goal for this turn?
2. What should be the next goal after this?
3. Is any goal being completed this turn?
4. What approach should the responder take?

Respond with JSON only:
{
  "current_goal": "goal for this turn",
  "next_goal": "goal after current is done",
  "goal_completed": "name of goal completed or null",
  "approach": "brief instruction for responder",
  "engagement": "low/medium/high/very_high"
}
PROMPT
                        ]
                    ]
                ],
                'options' => []
            ],
            'typeVersion' => 3.4
        ],

        // 9. Agent 2: STRATEGIST
        [
            'id' => 'agent_strategist',
            'name' => 'Strategist Agent',
            'type' => '@n8n/n8n-nodes-langchain.agent',
            'position' => [1540, 300],
            'parameters' => [
                'promptType' => 'define',
                'text' => '={{ $json.chatInput }}',
                'options' => [
                    'systemMessage' => 'You are a sales conversation strategist. Your job is to guide the conversation toward qualifying leads and booking meetings. Always respond with valid JSON only.'
                ]
            ],
            'typeVersion' => 1.7
        ],

        // 9b. OpenAI Model for Strategist
        [
            'id' => 'openai_strategist',
            'name' => 'OpenAI Strategist',
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'position' => [1540, 500],
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'options' => [
                    'temperature' => 0.5
                ]
            ],
            'typeVersion' => 1,
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 10. Parse Strategy & Update
        [
            'id' => 'parse_strategy',
            'name' => 'Parse Strategy',
            'type' => 'n8n-nodes-base.code',
            'position' => [1720, 300],
            'parameters' => [
                'jsCode' => <<<'CODE'
const response = $input.first().json.output || $input.first().json.text || '';
let strategy = {};

try {
  const jsonMatch = response.match(/\{[\s\S]*\}/);
  if (jsonMatch) {
    strategy = JSON.parse(jsonMatch[0]);
  }
} catch (e) {
  strategy = {
    current_goal: 'engage',
    approach: 'Be helpful and friendly',
    engagement: 'medium'
  };
}

const sessionId = $('Extract Input').first().json.session_id;
const message = $('Extract Input').first().json.message;
const analysis = $('Parse Analyzer').first().json.analysis;
const collected = $('Save Extraction').first().json.collected || $('Parse Analyzer').first().json.state?.collected || {};

return [{
  json: {
    session_id: sessionId,
    message: message,
    collected: collected,
    analysis: analysis,
    strategy: strategy
  }
}];
CODE
            ],
            'typeVersion' => 2
        ],

        // 11. Update Strategy in IRM
        [
            'id' => 'update_strategy',
            'name' => 'Update Strategy',
            'type' => 'n8n-nodes-base.httpRequest',
            'position' => [1900, 300],
            'parameters' => [
                'method' => 'POST',
                'url' => "$irmBaseUrl/update-strategy.php",
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id, current_goal: $json.strategy.current_goal, next_goal: $json.strategy.next_goal, goal_completed: $json.strategy.goal_completed, engagement: $json.strategy.engagement }) }}',
                'options' => []
            ],
            'typeVersion' => 4.2
        ],

        // 12. Prepare Responder Input
        [
            'id' => 'prep_responder',
            'name' => 'Prepare Responder',
            'type' => 'n8n-nodes-base.set',
            'position' => [2080, 300],
            'parameters' => [
                'mode' => 'manual',
                'duplicateItem' => false,
                'assignments' => [
                    'assignments' => [
                        ['id' => 'chatInput', 'name' => 'chatInput', 'type' => 'string', 'value' => <<<'PROMPT'
You are Ava, a friendly assistant for AbroadWorks.

USER SAID: {{ $('Extract Input').first().json.message }}

YOUR STRATEGY FOR THIS RESPONSE:
{{ $('Parse Strategy').first().json.strategy?.approach || 'Be helpful and friendly' }}

WHAT WE KNOW ABOUT USER:
{{ JSON.stringify($('Parse Strategy').first().json.collected || {}) }}

RULES:
1. Keep response SHORT (2-4 sentences max)
2. Sound natural and friendly, not robotic
3. If asking for info, ask for ONLY ONE thing
4. Match user's language (if they write Turkish, respond in Turkish)
5. NEVER ask for information we already have
6. Focus on current goal: {{ $('Parse Strategy').first().json.strategy?.current_goal || 'engage' }}

Generate your response now.
PROMPT
                        ]
                    ]
                ],
                'options' => []
            ],
            'typeVersion' => 3.4
        ],

        // 13. Agent 3: RESPONDER
        [
            'id' => 'agent_responder',
            'name' => 'Responder Agent',
            'type' => '@n8n/n8n-nodes-langchain.agent',
            'position' => [2260, 300],
            'parameters' => [
                'promptType' => 'define',
                'text' => '={{ $json.chatInput }}',
                'options' => [
                    'systemMessage' => 'You are Ava, a helpful and friendly assistant for AbroadWorks. Keep responses concise and natural. Never use markdown formatting. Just plain conversational text.'
                ]
            ],
            'typeVersion' => 1.7
        ],

        // 13b. OpenAI Model for Responder
        [
            'id' => 'openai_responder',
            'name' => 'OpenAI Responder',
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'position' => [2260, 500],
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'options' => [
                    'temperature' => 0.7,
                    'maxTokens' => 200
                ]
            ],
            'typeVersion' => 1,
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 14. Format Response
        [
            'id' => 'format_response',
            'name' => 'Format Response',
            'type' => 'n8n-nodes-base.code',
            'position' => [2440, 300],
            'parameters' => [
                'jsCode' => <<<'CODE'
const response = $input.first().json.output || $input.first().json.text || 'I apologize, I had trouble generating a response. How can I help you?';
const strategy = $('Parse Strategy').first().json.strategy || {};
const analysis = $('Parse Analyzer').first().json.analysis || {};
const sessionId = $('Extract Input').first().json.session_id;

return [{
  json: {
    response: response.trim(),
    session_id: sessionId,
    debug: {
      intent: analysis.intent,
      goal: strategy.current_goal,
      extracted: analysis.extracted
    }
  }
}];
CODE
            ],
            'typeVersion' => 2
        ],

        // 15. Respond to Webhook
        [
            'id' => 'respond',
            'name' => 'Respond',
            'type' => 'n8n-nodes-base.respondToWebhook',
            'position' => [2620, 300],
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
                'responseBody' => '={{ JSON.stringify({ response: $json.response, debug: $json.debug }) }}'
            ],
            'typeVersion' => 1.1
        ]
    ],

    'connections' => [
        'Webhook' => [
            'main' => [[['node' => 'Extract Input', 'type' => 'main', 'index' => 0]]]
        ],
        'Extract Input' => [
            'main' => [[['node' => 'Get State', 'type' => 'main', 'index' => 0]]]
        ],
        'Get State' => [
            'main' => [[['node' => 'Prepare Analyzer', 'type' => 'main', 'index' => 0]]]
        ],
        'Prepare Analyzer' => [
            'main' => [[['node' => 'Analyzer Agent', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI Analyzer' => [
            'ai_languageModel' => [[['node' => 'Analyzer Agent', 'type' => 'ai_languageModel', 'index' => 0]]]
        ],
        'Analyzer Agent' => [
            'main' => [[['node' => 'Parse Analyzer', 'type' => 'main', 'index' => 0]]]
        ],
        'Parse Analyzer' => [
            'main' => [[['node' => 'Save Extraction', 'type' => 'main', 'index' => 0]]]
        ],
        'Save Extraction' => [
            'main' => [[['node' => 'Prepare Strategist', 'type' => 'main', 'index' => 0]]]
        ],
        'Prepare Strategist' => [
            'main' => [[['node' => 'Strategist Agent', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI Strategist' => [
            'ai_languageModel' => [[['node' => 'Strategist Agent', 'type' => 'ai_languageModel', 'index' => 0]]]
        ],
        'Strategist Agent' => [
            'main' => [[['node' => 'Parse Strategy', 'type' => 'main', 'index' => 0]]]
        ],
        'Parse Strategy' => [
            'main' => [[['node' => 'Update Strategy', 'type' => 'main', 'index' => 0]]]
        ],
        'Update Strategy' => [
            'main' => [[['node' => 'Prepare Responder', 'type' => 'main', 'index' => 0]]]
        ],
        'Prepare Responder' => [
            'main' => [[['node' => 'Responder Agent', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI Responder' => [
            'ai_languageModel' => [[['node' => 'Responder Agent', 'type' => 'ai_languageModel', 'index' => 0]]]
        ],
        'Responder Agent' => [
            'main' => [[['node' => 'Format Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Format Response' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],

    'settings' => [
        'executionOrder' => 'v1'
    ]
];

// Update workflow via PUT
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
    echo "Updated workflow: {$result['id']}\n";
    echo "Name: {$result['name']}\n";

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
    echo "Webhook: https://n8n.abroadworks.com/webhook/aw-chat-v9\n";
} else {
    echo "Error ($httpCode): $response\n";
}
