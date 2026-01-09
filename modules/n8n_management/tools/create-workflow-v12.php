<?php
/**
 * Create n8n Workflow v12 - Real AI Agent Node
 * Uses @n8n/n8n-nodes-langchain.agent with tools
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

echo "=== Creating AW Chat v12 - Real AI Agent ===\n\n";

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

// System prompt for the AI Agent
$systemPrompt = 'You are an intelligent sales assistant for AbroadWorks, a company that provides virtual assistant and remote staffing services.

## YOUR CAPABILITIES:
You have access to tools that allow you to:
1. Search the knowledge base for company information
2. Save customer data (name, email, phone) when they provide it
3. Get the current conversation context and customer data

## YOUR BEHAVIOR:
1. ALWAYS use the kb_search tool when customers ask questions about services, pricing, process, etc.
2. When a customer provides their name, email, or phone, use the save_data tool to record it
3. Be helpful, professional, and conversational
4. Respond in the SAME LANGUAGE the customer uses (Turkish → Turkish, English → English)
5. If you cannot find information in the knowledge base, offer to connect them with a team member
6. After 3-4 exchanges, naturally ask for contact information if not yet provided

## COMPANY INFO:
- AbroadWorks provides virtual assistants, executive assistants, and specialized remote staff
- Services: Admin support, Customer service, Bookkeeping, Digital marketing, Development
- Pricing: $8-15/hour depending on role and experience
- We handle all HR, compliance, and payroll

## IMPORTANT:
- Never make up information - use knowledge base or say you will check with the team
- Keep responses concise (2-4 sentences)
- Be proactive in guiding the conversation toward scheduling a call';

// Workflow with real AI Agent node
$workflow = [
    'name' => 'AW Chat v12 - Real AI Agent',
    'nodes' => [
        // 1. Webhook Trigger
        [
            'parameters' => [
                'httpMethod' => 'POST',
                'path' => 'aw-chat-v12',
                'responseMode' => 'responseNode',
                'options' => new stdClass()
            ],
            'id' => 'webhook-trigger',
            'name' => 'Chat Webhook',
            'type' => 'n8n-nodes-base.webhook',
            'typeVersion' => 2,
            'position' => [200, 300],
            'webhookId' => 'aw-chat-v12'
        ],

        // 2. Prepare Input
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => '
const body = $("Chat Webhook").first().json.body;
return [{
  json: {
    sessionId: body.session_id,
    message: body.message,
    visitorId: body.visitor_id
  }
}];'
            ],
            'id' => 'prepare-input',
            'name' => 'Prepare Input',
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [400, 300]
        ],

        // 3. Get Context Tool (will be connected to Agent)
        [
            'parameters' => [
                'method' => 'POST',
                'url' => 'https://irm.abroadworks.com/modules/n8n_management/api/tools/get-state.php',
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.sessionId, message: $json.message }) }}',
                'options' => ['timeout' => 30000]
            ],
            'id' => 'get-context',
            'name' => 'Get Context',
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [600, 300]
        ],

        // 4. Build Agent Input with Context
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => '
const input = $("Prepare Input").first().json;
const contextResp = $("Get Context").first().json;
const state = contextResp.state || {};

// Build context string
let context = "";

// Add conversation history
if (state.history_summary && state.history_summary.length > 0) {
  context += "## Previous conversation:\\n" + state.history_summary.slice(-6).join("\\n") + "\\n\\n";
}

// Add collected data
if (state.collected_data && Object.keys(state.collected_data).length > 0) {
  context += "## Customer data collected so far:\\n" + JSON.stringify(state.collected_data) + "\\n\\n";
}

// Add missing data
if (state.missing_data && state.missing_data.length > 0) {
  context += "## Data still needed: " + state.missing_data.join(", ") + "\\n\\n";
}

// Add KB results if available
if (state.kb_results && state.kb_results.length > 0) {
  context += "## Relevant knowledge base entries:\\n";
  state.kb_results.forEach((kb, i) => {
    context += (i+1) + ". " + kb.question + "\\n   Answer: " + kb.answer + "\\n";
  });
  context += "\\n";
}

// Build the user message with context
const userMessage = context + "## Current customer message:\\n" + input.message;

return [{
  json: {
    sessionId: input.sessionId,
    userMessage: userMessage,
    rawMessage: input.message,
    state: state
  }
}];'
            ],
            'id' => 'build-agent-input',
            'name' => 'Build Agent Input',
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [800, 300]
        ],

        // 5. OpenAI Chat Model (connected to Agent)
        [
            'parameters' => [
                'model' => 'gpt-4o',
                'options' => [
                    'temperature' => 0.7,
                    'maxTokens' => 1000
                ]
            ],
            'id' => 'openai-model',
            'name' => 'OpenAI GPT-4o',
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'typeVersion' => 1,
            'position' => [1000, 500],
            'credentials' => [
                'openAiApi' => [
                    'id' => 'openai',
                    'name' => 'OpenAI'
                ]
            ]
        ],

        // 6. KB Search Tool
        [
            'parameters' => [
                'name' => 'kb_search',
                'description' => 'Search the AbroadWorks knowledge base. Use this tool when customers ask about services, pricing, process, company info, etc. Input should be the search query.',
                'method' => 'POST',
                'url' => 'https://irm.abroadworks.com/modules/n8n_management/api/tools/kb-search.php',
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ query: $fromAI("query", "The search query to find relevant information") }) }}'
            ],
            'id' => 'kb-search-tool',
            'name' => 'KB Search Tool',
            'type' => '@n8n/n8n-nodes-langchain.toolHttpRequest',
            'typeVersion' => 1.1,
            'position' => [1000, 700]
        ],

        // 7. Save Data Tool
        [
            'parameters' => [
                'name' => 'save_customer_data',
                'description' => 'Save customer information when they provide their name, email, or phone number. Call this tool whenever a customer shares contact information.',
                'method' => 'POST',
                'url' => 'https://irm.abroadworks.com/modules/n8n_management/api/tools/save-extraction.php',
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Build Agent Input").first().json.sessionId, extracted_data: { name: $fromAI("name", "Customer name if provided, otherwise empty string"), email: $fromAI("email", "Customer email if provided, otherwise empty string"), phone: $fromAI("phone", "Customer phone if provided, otherwise empty string"), intent: $fromAI("intent", "Customer intent: services, pricing, booking, support, or other") } }) }}'
            ],
            'id' => 'save-data-tool',
            'name' => 'Save Data Tool',
            'type' => '@n8n/n8n-nodes-langchain.toolHttpRequest',
            'typeVersion' => 1.1,
            'position' => [1200, 700]
        ],

        // 8. AI Agent Node
        [
            'parameters' => [
                'options' => [
                    'systemMessage' => $systemPrompt
                ]
            ],
            'id' => 'ai-agent',
            'name' => 'Sales AI Agent',
            'type' => '@n8n/n8n-nodes-langchain.agent',
            'typeVersion' => 1.6,
            'position' => [1100, 300]
        ],

        // 9. Format Response
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => '
const agentOutput = $("Sales AI Agent").first().json;
const input = $("Build Agent Input").first().json;

// Get the agent response
let response = "";
if (agentOutput.output) {
  response = agentOutput.output;
} else if (agentOutput.text) {
  response = agentOutput.text;
} else {
  response = "I apologize, I am having trouble processing that. Could you please rephrase?";
}

return [{
  json: {
    success: true,
    response: response,
    sessionId: input.sessionId,
    debug: {
      hadKbResults: (input.state.kb_results || []).length > 0,
      missingData: input.state.missing_data || []
    }
  }
}];'
            ],
            'id' => 'format-response',
            'name' => 'Format Response',
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1300, 300]
        ],

        // 10. Respond to Webhook
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify($json) }}',
                'options' => new stdClass()
            ],
            'id' => 'respond-webhook',
            'name' => 'Send Response',
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [1500, 300]
        ]
    ],
    'connections' => [
        'Chat Webhook' => [
            'main' => [[['node' => 'Prepare Input', 'type' => 'main', 'index' => 0]]]
        ],
        'Prepare Input' => [
            'main' => [[['node' => 'Get Context', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Context' => [
            'main' => [[['node' => 'Build Agent Input', 'type' => 'main', 'index' => 0]]]
        ],
        'Build Agent Input' => [
            'main' => [[['node' => 'Sales AI Agent', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI GPT-4o' => [
            'ai_languageModel' => [[['node' => 'Sales AI Agent', 'type' => 'ai_languageModel', 'index' => 0]]]
        ],
        'KB Search Tool' => [
            'ai_tool' => [[['node' => 'Sales AI Agent', 'type' => 'ai_tool', 'index' => 0]]]
        ],
        'Save Data Tool' => [
            'ai_tool' => [[['node' => 'Sales AI Agent', 'type' => 'ai_tool', 'index' => 0]]]
        ],
        'Sales AI Agent' => [
            'main' => [[['node' => 'Format Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Format Response' => [
            'main' => [[['node' => 'Send Response', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => [
        'executionOrder' => 'v1'
    ]
];

// Create workflow
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

    // Activate
    $ch = curl_init("$n8nUrl/api/v1/workflows/{$result['id']}/activate");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['X-N8N-API-KEY: ' . $apiKey]
    ]);
    curl_exec($ch);
    curl_close($ch);

    echo "✓ Workflow activated\n";
    echo "\nWebhook URL: https://n8n.abroadworks.com/webhook/aw-chat-v12\n";

    // Update orchestrator
    $orchestratorPath = dirname(__DIR__) . '/api/chat/orchestrator.php';
    $content = file_get_contents($orchestratorPath);
    $content = preg_replace('/webhook\/aw-chat-v\d+/', 'webhook/aw-chat-v12', $content);
    file_put_contents($orchestratorPath, $content);
    echo "✓ Orchestrator updated to use v12\n";

} else {
    echo "✗ Failed to create workflow\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
}

echo "\n=== Done ===\n";
