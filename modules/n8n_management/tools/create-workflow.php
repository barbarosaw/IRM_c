<?php
/**
 * Create n8n Workflow for AbroadWorks Chatbot
 * Run: php create-workflow.php
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

// Get n8n settings
$stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_key', 'n8n_chat_api_key')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$n8nHost = rtrim($settings['n8n_host'] ?? '', '/');
$n8nApiKey = $settings['n8n_api_key'] ?? '';
$chatApiKey = $settings['n8n_chat_api_key'] ?? '';

if (!$n8nHost || !$n8nApiKey) {
    die("Error: n8n is not configured\n");
}

// IRM API endpoints
$irmBaseUrl = 'https://irm.abroadworks.com';
$sessionUrl = $irmBaseUrl . '/modules/n8n_management/api/chat/session.php';
$messageUrl = $irmBaseUrl . '/modules/n8n_management/api/chat/message.php';
$endSessionUrl = $irmBaseUrl . '/modules/n8n_management/api/chat/end-session.php';

// Workflow definition
$workflow = [
    'name' => 'AbroadWorks Chatbot - IRM Integration',
    'nodes' => [
        // 1. Webhook Trigger
        [
            'parameters' => [
                'path' => 'abroadworks-chat',
                'httpMethod' => 'POST',
                'responseMode' => 'responseNode',
                'options' => [
                    'responseHeaders' => [
                        'entries' => [
                            ['name' => 'Access-Control-Allow-Origin', 'value' => '*']
                        ]
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.webhook',
            'typeVersion' => 2,
            'position' => [0, 0],
            'id' => 'webhook-trigger',
            'name' => 'Chat Webhook',
            'webhookId' => 'abroadworks-chat-' . bin2hex(random_bytes(8))
        ],
        // 2. Session Manager - HTTP Request to IRM
        [
            'parameters' => [
                'url' => $sessionUrl,
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey],
                        ['name' => 'Content-Type', 'value' => 'application/json']
                    ]
                ],
                'sendBody' => true,
                'bodyParameters' => [
                    'parameters' => [
                        ['name' => 'visitor_id', 'value' => '={{ $json.visitor_id }}'],
                        ['name' => 'page_url', 'value' => '={{ $json.page_url }}'],
                        ['name' => 'page_title', 'value' => '={{ $json.page_title }}'],
                        ['name' => 'user_agent', 'value' => '={{ $json.user_agent }}']
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [250, 0],
            'id' => 'session-manager',
            'name' => 'Create/Get Session'
        ],
        // 3. Save User Message
        [
            'parameters' => [
                'url' => $messageUrl,
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey],
                        ['name' => 'Content-Type', 'value' => 'application/json']
                    ]
                ],
                'sendBody' => true,
                'bodyParameters' => [
                    'parameters' => [
                        ['name' => 'session_id', 'value' => '={{ $("Create/Get Session").item.json.session_id }}'],
                        ['name' => 'role', 'value' => 'user'],
                        ['name' => 'content', 'value' => '={{ $("Chat Webhook").item.json.message }}'],
                        ['name' => 'intent', 'value' => '={{ $("Chat Webhook").item.json.intent }}']
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [500, 0],
            'id' => 'save-user-message',
            'name' => 'Save User Message'
        ],
        // 4. Knowledge Base Code Node
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => '
const knowledge = {
  services: {
    virtual_assistant: `
VIRTUAL ASSISTANT SERVICES
- Dedicated remote professionals for your business
- Available part-time (20hrs/week) or full-time (40hrs/week)
- Pricing: $8-15/hour depending on skills and experience
- Tasks: Administrative support, customer service, data entry, scheduling, email management
- All VAs are pre-vetted, English proficient, and trained
- 2-week trial period available
- 30-day replacement guarantee
    `,
    staffing: `
STAFFING SOLUTIONS
- Long-term dedicated staff for your operations
- Full-time employees working exclusively for you
- Pricing: $10-20/hour based on role complexity
- Includes: Recruitment, training, ongoing management
- Roles: Customer support, sales, accounting, marketing, IT support
- Seamless integration with your team
    `,
    recruitment: `
RECRUITMENT SERVICES
- End-to-end hiring solutions for US companies
- Pricing: 15-25% of first year salary
- Process: Job posting, sourcing, screening, interviews, background checks
- Average time to hire: 2-4 weeks
- Specialties: Tech, healthcare, finance, customer service
    `
  },
  company: {
    about: `
ABOUT ABROADWORKS
- US-based company specializing in offshore staffing solutions
- Founded in 2018, serving 200+ clients
- Headquarters: Texas, USA
- Mission: Help businesses scale efficiently with dedicated remote professionals
    `,
    contact: `
CONTACT INFORMATION
- Email: info@abroadworks.com
- Phone: +1 (555) 123-4567
- Website: www.abroadworks.com
- Business Hours: Monday-Friday, 9AM-6PM EST
- Book a consultation: calendly.com/abroadworks
    `
  },
  faq: `
FREQUENTLY ASKED QUESTIONS

Q: How quickly can I get started?
A: We can have qualified candidates ready within 1-2 weeks.

Q: What if the candidate does not work out?
A: We offer a 30-day replacement guarantee at no additional cost.

Q: Do you offer trial periods?
A: Yes, we offer 2-week trial periods for all services.

Q: What time zones do your staff work in?
A: Our staff can work in any timezone that matches your business hours.

Q: How do you ensure quality?
A: All candidates go through rigorous vetting, skills testing, and background checks.
  `
};

const inputItems = $input.all();
const webhookData = $("Chat Webhook").first().json;
const message = webhookData.message || "";
const intent = webhookData.intent || "general";
const lowerMessage = message.toLowerCase();

let relevantKnowledge = "";

// Determine relevant knowledge based on intent and message content
if (intent === "services" || lowerMessage.includes("service") || lowerMessage.includes("virtual") || lowerMessage.includes("staff")) {
  if (lowerMessage.includes("virtual") || lowerMessage.includes("va") || lowerMessage.includes("assistant")) {
    relevantKnowledge = knowledge.services.virtual_assistant;
  } else if (lowerMessage.includes("staff")) {
    relevantKnowledge = knowledge.services.staffing;
  } else if (lowerMessage.includes("recruit") || lowerMessage.includes("hiring")) {
    relevantKnowledge = knowledge.services.recruitment;
  } else {
    relevantKnowledge = Object.values(knowledge.services).join("\\n\\n");
  }
} else if (intent === "booking" || lowerMessage.includes("book") || lowerMessage.includes("call") || lowerMessage.includes("meeting")) {
  relevantKnowledge = knowledge.company.contact + "\\n\\n" + knowledge.services.virtual_assistant;
} else if (intent === "careers" || lowerMessage.includes("job") || lowerMessage.includes("career") || lowerMessage.includes("work for")) {
  relevantKnowledge = knowledge.company.about + "\\n\\nFor career opportunities, please send your resume to careers@abroadworks.com";
} else {
  relevantKnowledge = knowledge.company.about + "\\n\\n" + knowledge.company.contact + "\\n\\n" + knowledge.faq;
}

return [{
  json: {
    knowledge: relevantKnowledge,
    message: message,
    intent: intent,
    session_id: $("Create/Get Session").first().json.session_id
  }
}];
'
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [750, 0],
            'id' => 'knowledge-base',
            'name' => 'Knowledge Base'
        ],
        // 5. AI Agent with System Prompt
        [
            'parameters' => [
                'options' => [
                    'systemMessage' => 'You are AbroadWorks customer service assistant. You are helpful, professional, and concise.

KNOWLEDGE BASE:
{{ $json.knowledge }}

GUIDELINES:
- Keep responses under 150 words
- Be friendly and professional
- If asked about pricing, provide ranges from knowledge base
- Encourage booking a free consultation for detailed quotes
- Never make up information not in the knowledge base
- If you do not know something, say so and offer to connect with a human
- Always be helpful and guide users to the right service'
                ]
            ],
            'type' => '@n8n/n8n-nodes-langchain.agent',
            'typeVersion' => 1.7,
            'position' => [1000, 0],
            'id' => 'ai-agent',
            'name' => 'AI Agent'
        ],
        // 6. OpenAI Chat Model
        [
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'options' => [
                    'temperature' => 0.7,
                    'maxTokens' => 300
                ]
            ],
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'typeVersion' => 1,
            'position' => [1000, 200],
            'id' => 'openai-model',
            'name' => 'OpenAI Chat Model',
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],
        // 7. Memory
        [
            'parameters' => [
                'contextWindowLength' => 10
            ],
            'type' => '@n8n/n8n-nodes-langchain.memoryBufferWindow',
            'typeVersion' => 1.3,
            'position' => [1200, 200],
            'id' => 'memory',
            'name' => 'Window Buffer Memory'
        ],
        // 8. Save Bot Response
        [
            'parameters' => [
                'url' => $messageUrl,
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey],
                        ['name' => 'Content-Type', 'value' => 'application/json']
                    ]
                ],
                'sendBody' => true,
                'bodyParameters' => [
                    'parameters' => [
                        ['name' => 'session_id', 'value' => '={{ $("Knowledge Base").item.json.session_id }}'],
                        ['name' => 'role', 'value' => 'assistant'],
                        ['name' => 'content', 'value' => '={{ $json.output }}']
                    ]
                ]
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1250, 0],
            'id' => 'save-bot-response',
            'name' => 'Save Bot Response'
        ],
        // 9. Respond to Webhook
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={
  "success": true,
  "response": {{ JSON.stringify($("AI Agent").item.json.output) }},
  "session_id": "{{ $("Knowledge Base").item.json.session_id }}",
  "intent": "{{ $("Knowledge Base").item.json.intent }}"
}'
            ],
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [1500, 0],
            'id' => 'respond-webhook',
            'name' => 'Respond to Widget'
        ]
    ],
    'connections' => [
        'Chat Webhook' => [
            'main' => [[['node' => 'Create/Get Session', 'type' => 'main', 'index' => 0]]]
        ],
        'Create/Get Session' => [
            'main' => [[['node' => 'Save User Message', 'type' => 'main', 'index' => 0]]]
        ],
        'Save User Message' => [
            'main' => [[['node' => 'Knowledge Base', 'type' => 'main', 'index' => 0]]]
        ],
        'Knowledge Base' => [
            'main' => [[['node' => 'AI Agent', 'type' => 'main', 'index' => 0]]]
        ],
        'OpenAI Chat Model' => [
            'ai_languageModel' => [[['node' => 'AI Agent', 'type' => 'ai_languageModel', 'index' => 0]]]
        ],
        'Window Buffer Memory' => [
            'ai_memory' => [[['node' => 'AI Agent', 'type' => 'ai_memory', 'index' => 0]]]
        ],
        'AI Agent' => [
            'main' => [[['node' => 'Save Bot Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Save Bot Response' => [
            'main' => [[['node' => 'Respond to Widget', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => [
        'executionOrder' => 'v1'
    ]
];

// Create workflow via n8n API
$url = $n8nHost . '/api/v1/workflows';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'X-N8N-API-KEY: ' . $n8nApiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($workflow)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "cURL Error: $error\n";
    exit(1);
}

$result = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300) {
    echo "Workflow created successfully!\n";
    echo "ID: " . ($result['id'] ?? 'unknown') . "\n";
    echo "Name: " . ($result['name'] ?? 'unknown') . "\n";

    // Get the webhook URL
    if (isset($result['id'])) {
        $webhookUrl = $n8nHost . '/webhook/abroadworks-chat';
        echo "\nWebhook URL: $webhookUrl\n";
        echo "\nDon't forget to:\n";
        echo "1. Activate the workflow in n8n\n";
        echo "2. Add the webhook URL to Widget Settings in IRM\n";
    }
} else {
    echo "Error creating workflow (HTTP $httpCode):\n";
    echo $response . "\n";
}
