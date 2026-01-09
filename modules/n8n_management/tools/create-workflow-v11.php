<?php
/**
 * Create n8n Workflow v11 - Intelligent Manager Agent
 * Single AI agent that handles all decisions
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

echo "=== Creating AW Chat v11 Workflow ===\n\n";

// Get n8n credentials
$stmt = $db->query("SELECT `key`, value FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_url', 'n8n_api_key')");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}

$n8nUrl = rtrim($settings['n8n_host'] ?? $settings['n8n_api_url'] ?? '', '/');
$apiKey = $settings['n8n_api_key'] ?? '';

if (!$n8nUrl || !$apiKey) {
    die("Error: n8n API credentials not configured\n");
}

// Build Prompt JS Code
$buildPromptCode = 'const webhookData = $("Webhook").first().json.body;
const contextResponse = $("Get Context").first().json;

const state = contextResponse.state || {};
const message = webhookData.message || state.last_message || "";
const sessionId = webhookData.session_id;

// Build KB context if available
let kbContext = "";
if (state.kb_results && state.kb_results.length > 0) {
  kbContext = "\\n\\n## KNOWLEDGE BASE MATCHES:\\n";
  state.kb_results.forEach((kb, i) => {
    kbContext += (i+1) + ". Q: " + kb.question + "\\n   A: " + kb.answer + "\\n   Category: " + kb.category + "\\n\\n";
  });
}

// Build conversation history
let historyContext = "";
if (state.history_summary && state.history_summary.length > 0) {
  historyContext = "\\n\\n## CONVERSATION HISTORY:\\n" + state.history_summary.join("\\n");
}

// Build data collection status
let dataStatus = "\\n\\n## DATA COLLECTION STATUS:\\n";
if (state.collected_data) {
  dataStatus += "Collected: " + JSON.stringify(state.collected_data) + "\\n";
}
if (state.missing_data && state.missing_data.length > 0) {
  dataStatus += "Missing: " + state.missing_data.join(", ") + "\\n";
} else {
  dataStatus += "All required data collected!\\n";
}

// Analysis hints
let analysisHints = "\\n\\n## ANALYSIS:\\n";
analysisHints += "- Turn count: " + (state.turn_count || 0) + "\\n";
analysisHints += "- Is question: " + (state.is_question ? "Yes" : "No") + "\\n";
analysisHints += "- Repetition score: " + (state.repetition_score || 0) + "\\n";
analysisHints += "- Suggested action: " + (state.suggested_action || "continue") + "\\n";

// Custom system prompt from settings
const customPrompt = state.system_prompt || "";

const systemPrompt = `You are an intelligent sales assistant for AbroadWorks, a company that provides virtual assistant staffing services.

## YOUR ROLE:
You are the MANAGER AGENT. You must:
1. Analyze the customer message and conversation context
2. Decide the best action to take
3. Generate an appropriate response
4. Extract any new information shared by the customer

## COMPANY INFO:
- AbroadWorks provides virtual assistants, executive assistants, and specialized remote staff
- Services include: Admin support, Customer service, Bookkeeping, Digital marketing, Development
- Pricing typically ranges from $8-15/hour depending on role and experience
- We handle all HR, compliance, payroll for hired staff

## DECISION RULES:
1. If customer asks a QUESTION -> Use Knowledge Base matches to answer accurately
2. If customer provides INFO (name, email, phone) -> Extract and acknowledge it
3. If data is MISSING and conversation has 3+ turns -> Naturally ask for missing info
4. If REPETITION score is high (3+) -> Change approach, offer live call or redirect
5. If customer seems READY -> Offer to schedule a call/demo
6. NEVER make up information - use only KB matches or say "Let me connect you with our team"

${customPrompt ? "\\n## CUSTOM INSTRUCTIONS:\\n" + customPrompt : ""}
${kbContext}
${historyContext}
${dataStatus}
${analysisHints}

## CURRENT MESSAGE FROM CUSTOMER:
"${message}"

## YOUR TASK:
Respond with a JSON object containing:
{
  "thinking": "Your internal reasoning about what to do (1-2 sentences)",
  "decision": "answer_question | collect_data | provide_info | redirect_live | schedule_call",
  "extracted_data": {
    "name": "if mentioned",
    "email": "if mentioned",
    "phone": "if mentioned",
    "company": "if mentioned",
    "need": "what they are looking for",
    "intent": "services | pricing | hiring | support | other"
  },
  "response": "Your natural, helpful response to the customer (in same language they used)"
}

IMPORTANT:
- Respond in the SAME LANGUAGE the customer uses (Turkish -> Turkish, English -> English)
- Be conversational and helpful, not robotic
- If you do not know something, offer to connect them with a team member
- Keep responses concise but complete (2-4 sentences typically)
- extracted_data should only include fields that were actually mentioned`;

return [{
  json: {
    systemPrompt,
    userMessage: message,
    sessionId,
    state,
    collectedData: state.collected_data || {},
    missingData: state.missing_data || []
  }
}];';

// Parse Response JS Code
$parseResponseCode = 'const promptData = $("Build Prompt").first().json;
const openaiResponse = $("Manager Agent").first().json;

let aiResult = {};
try {
  const content = openaiResponse.choices[0].message.content;
  aiResult = JSON.parse(content);
} catch (e) {
  aiResult = {
    response: "I apologize, I am having trouble processing that. Could you please rephrase?",
    decision: "error",
    extracted_data: {}
  };
}

// Merge extracted data with existing
const existingData = promptData.collectedData || {};
const newData = aiResult.extracted_data || {};
const mergedData = { ...existingData };

// Only add non-empty new values
for (const [key, value] of Object.entries(newData)) {
  if (value && value !== "null" && value !== "undefined" && typeof value === "string" && value.trim() !== "") {
    mergedData[key] = value;
  }
}

return [{
  json: {
    sessionId: promptData.sessionId,
    response: aiResult.response || "I am here to help! What would you like to know about our services?",
    decision: aiResult.decision || "continue",
    thinking: aiResult.thinking || "",
    extractedData: mergedData,
    newlyExtracted: newData,
    intent: newData.intent || null
  }
}];';

// OpenAI Request Body
$openaiBody = '={
  "model": "gpt-4o",
  "messages": [
    {
      "role": "system",
      "content": $json.systemPrompt
    },
    {
      "role": "user",
      "content": $json.userMessage
    }
  ],
  "response_format": { "type": "json_object" },
  "temperature": 0.7,
  "max_tokens": 1000
}';

// Workflow definition
$workflow = [
    'name' => 'AW Chat v11 - Intelligent Manager',
    'nodes' => [
        // 1. Webhook Trigger
        [
            'parameters' => [
                'httpMethod' => 'POST',
                'path' => 'aw-chat-v11',
                'responseMode' => 'responseNode',
                'options' => new stdClass()
            ],
            'id' => 'webhook',
            'name' => 'Webhook',
            'type' => 'n8n-nodes-base.webhook',
            'typeVersion' => 2,
            'position' => [250, 300],
            'webhookId' => 'aw-chat-v11'
        ],

        // 2. Get Context from IRM
        [
            'parameters' => [
                'method' => 'POST',
                'url' => 'https://irm.abroadworks.com/modules/n8n_management/api/tools/get-state.php',
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.body.session_id, message: $json.body.message }) }}',
                'options' => [
                    'timeout' => 30000
                ]
            ],
            'id' => 'get-context',
            'name' => 'Get Context',
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [470, 300]
        ],

        // 3. Build Manager Prompt
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => $buildPromptCode
            ],
            'id' => 'build-prompt',
            'name' => 'Build Prompt',
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [690, 300]
        ],

        // 4. OpenAI Manager Agent
        [
            'parameters' => [
                'method' => 'POST',
                'url' => 'https://api.openai.com/v1/chat/completions',
                'authentication' => 'predefinedCredentialType',
                'nodeCredentialType' => 'openAiApi',
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => $openaiBody,
                'options' => [
                    'timeout' => 60000
                ]
            ],
            'id' => 'openai-manager',
            'name' => 'Manager Agent',
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [910, 300],
            'credentials' => [
                'openAiApi' => [
                    'id' => 'openai',
                    'name' => 'OpenAI'
                ]
            ]
        ],

        // 5. Parse Response & Save Data
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => $parseResponseCode
            ],
            'id' => 'parse-response',
            'name' => 'Parse & Merge',
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1130, 300]
        ],

        // 6. Save to IRM
        [
            'parameters' => [
                'method' => 'POST',
                'url' => 'https://irm.abroadworks.com/modules/n8n_management/api/tools/save-extraction.php',
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.sessionId, extracted_data: $json.extractedData, intent: $json.intent, decision: $json.decision }) }}',
                'options' => [
                    'timeout' => 10000
                ]
            ],
            'id' => 'save-data',
            'name' => 'Save Data',
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1350, 300]
        ],

        // 7. Respond to Webhook
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify({ success: true, response: $("Parse & Merge").first().json.response, decision: $("Parse & Merge").first().json.decision, debug: { thinking: $("Parse & Merge").first().json.thinking } }) }}',
                'options' => new stdClass()
            ],
            'id' => 'respond',
            'name' => 'Respond',
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [1570, 300]
        ]
    ],
    'connections' => [
        'Webhook' => [
            'main' => [[['node' => 'Get Context', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Context' => [
            'main' => [[['node' => 'Build Prompt', 'type' => 'main', 'index' => 0]]]
        ],
        'Build Prompt' => [
            'main' => [[['node' => 'Manager Agent', 'type' => 'main', 'index' => 0]]]
        ],
        'Manager Agent' => [
            'main' => [[['node' => 'Parse & Merge', 'type' => 'main', 'index' => 0]]]
        ],
        'Parse & Merge' => [
            'main' => [[['node' => 'Save Data', 'type' => 'main', 'index' => 0]]]
        ],
        'Save Data' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => [
        'executionOrder' => 'v1'
    ]
];

// Create workflow via API
$ch = curl_init("$n8nUrl/api/v1/workflows");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($workflow),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-N8N-API-KEY: ' . $apiKey
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
    echo "✓ Workflow created: {$result['id']}\n";
    echo "  Name: {$result['name']}\n\n";

    // Activate workflow
    $ch = curl_init("$n8nUrl/api/v1/workflows/{$result['id']}/activate");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'X-N8N-API-KEY: ' . $apiKey
        ]
    ]);
    $activateResponse = curl_exec($ch);
    curl_close($ch);

    echo "✓ Workflow activated\n";
    echo "\nWebhook URL: https://n8n.abroadworks.com/webhook/aw-chat-v11\n";

    // Update orchestrator to use v11
    echo "\n--- Updating Orchestrator ---\n";

    $orchestratorPath = dirname(__DIR__) . '/api/chat/orchestrator.php';
    $orchestratorContent = file_get_contents($orchestratorPath);

    // Update webhook URL
    $orchestratorContent = preg_replace(
        '/webhook\/aw-chat-v\d+/',
        'webhook/aw-chat-v11',
        $orchestratorContent
    );

    file_put_contents($orchestratorPath, $orchestratorContent);
    echo "✓ Orchestrator updated to use v11\n";

} else {
    echo "✗ Failed to create workflow\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
}

echo "\n=== Done ===\n";
