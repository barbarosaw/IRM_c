<?php
/**
 * Create Advanced n8n Workflow v3 - No Merge Node
 * Each handler directly connects to Save+Respond chain
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

$stmt = $db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_key', 'n8n_chat_api_key')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$n8nHost = rtrim($settings['n8n_host'] ?? '', '/');
$n8nApiKey = $settings['n8n_api_key'] ?? '';
$chatApiKey = $settings['n8n_chat_api_key'] ?? '';

if (!$n8nHost || !$n8nApiKey) {
    die("Error: n8n is not configured\n");
}

$irmBaseUrl = 'https://irm.abroadworks.com';
$sessionUrl = $irmBaseUrl . '/modules/n8n_management/api/chat/session.php';
$messageUrl = $irmBaseUrl . '/modules/n8n_management/api/chat/message.php';
$updateSessionUrl = $irmBaseUrl . '/modules/n8n_management/api/chat/update-session.php';

// Knowledge Base
$knowledgeBase = <<<'KB'
const KNOWLEDGE = {
  va: {
    name: "Virtual Assistant Services",
    details: `
- Available part-time (20hrs/week) or full-time (40hrs/week)
- Pricing: $8-15/hour depending on skills
- Tasks: Admin support, customer service, data entry, scheduling, email management
- All VAs are pre-vetted and English proficient
- 2-week trial period, 30-day replacement guarantee`
  },
  staffing: {
    name: "Staffing Solutions",
    details: `
- Full-time dedicated employees for your operations
- Pricing: $10-20/hour based on role
- Includes: Recruitment, training, ongoing management
- Roles: Customer support, sales, accounting, marketing, IT
- Seamless integration with your team`
  },
  recruitment: {
    name: "Recruitment Services",
    details: `
- End-to-end hiring solutions
- Pricing: 15-25% of first year salary
- Process: Job posting, sourcing, screening, interviews, background checks
- Average time to hire: 2-4 weeks
- Specialties: Tech, healthcare, finance, customer service`
  },
  company: {
    about: `AbroadWorks - US-based offshore staffing company, founded 2018, serving 200+ clients`,
    contact: `Email: info@abroadworks.com | Hours: Mon-Fri 10AM-6PM EST`
  }
};
KB;

// System prompt for AI
$systemPrompt = <<<'PROMPT'
You are AbroadWorks customer service assistant. Be helpful, professional, and concise.

CRITICAL RULES:
1. Only answer based on the KNOWLEDGE provided
2. Never make up information
3. Keep responses under 150 words
4. For detailed pricing quotes, encourage booking a free consultation
5. Be friendly and guide users to appropriate services

KNOWLEDGE:
{{knowledge}}

Current context:
- User's intent: {{intent}}
- Service topic: {{topic}}
PROMPT;

$workflow = [
    'name' => 'AbroadWorks Chatbot v3',
    'nodes' => [
        // 1. Webhook Trigger
        [
            'parameters' => [
                'path' => 'aw-chat-v3',
                'httpMethod' => 'POST',
                'responseMode' => 'responseNode',
                'options' => []
            ],
            'type' => 'n8n-nodes-base.webhook',
            'typeVersion' => 2,
            'position' => [0, 300],
            'id' => 'webhook',
            'name' => 'Chat Webhook',
            'webhookId' => 'aw-chat-v3'
        ],

        // 2. Session Manager
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
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ visitor_id: $json.visitor_id, page_url: $json.page_url }) }}'
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
                'url' => $messageUrl,
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $("Get Session").item.json.session_id, role: "user", content: $("Chat Webhook").item.json.message }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [440, 300],
            'id' => 'save-user',
            'name' => 'Save User Message'
        ],

        // 4. Prepare & Classify
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const webhook = $("Chat Webhook").first().json;
const session = $("Get Session").first().json;
const message = (webhook.message || "").toLowerCase();
const ctx = webhook.session_context || {};

// Simple intent detection
let intent = "general";
let topic = "company";

// Job application
if (/\b(job|career|apply|hiring|work for you|employment|position|resume|cv)\b/i.test(message)) {
  intent = "job_application";
}
// Booking
else if (/\b(book|schedule|call|meeting|consultation|demo|appointment|talk|speak)\b/i.test(message)) {
  intent = "booking";
}
// Off-topic / manipulation
else if (/\b(ignore|forget|pretend|roleplay|act as|you are now)\b/i.test(message)) {
  intent = "manipulation";
}
else if (/\b(weather|sport|movie|game|joke|sing|poem|story)\b/i.test(message)) {
  intent = "off_topic";
}
// Services
else if (/\b(virtual|assistant|va|admin|secretary)\b/i.test(message)) {
  intent = "services"; topic = "va";
}
else if (/\b(staff|staffing|team|dedicated|employee|full-time)\b/i.test(message)) {
  intent = "services"; topic = "staffing";
}
else if (/\b(recruit|hiring|headhunt|talent|hire)\b/i.test(message)) {
  intent = "services"; topic = "recruitment";
}
else if (/\b(price|cost|rate|how much|pricing|fee)\b/i.test(message)) {
  intent = "services"; topic = "pricing";
}
else if (/\b(service|help|offer|provide|do you)\b/i.test(message)) {
  intent = "services"; topic = "general";
}

// Check if returning job seeker
const isJobSeeker = ctx.is_job_seeker || session.is_job_seeker || false;
if (isJobSeeker && intent === "services") {
  intent = "job_seeker_service";
}

return [{
  json: {
    message: webhook.message,
    session_id: session.session_id,
    intent: intent,
    topic: topic,
    is_job_seeker: isJobSeeker,
    off_topic_attempts: ctx.off_topic_attempts || session.off_topic_attempts || 0
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [660, 300],
            'id' => 'classify',
            'name' => 'Classify Intent'
        ],

        // 5. Router Switch
        [
            'parameters' => [
                'rules' => [
                    'values' => [
                        [
                            'conditions' => [
                                'options' => ['version' => 2],
                                'combinator' => 'and',
                                'conditions' => [
                                    ['leftValue' => '={{ $json.intent }}', 'rightValue' => 'job_application', 'operator' => ['type' => 'string', 'operation' => 'equals']]
                                ]
                            ],
                            'renameOutput' => true,
                            'outputKey' => 'Job'
                        ],
                        [
                            'conditions' => [
                                'options' => ['version' => 2],
                                'combinator' => 'and',
                                'conditions' => [
                                    ['leftValue' => '={{ $json.intent }}', 'rightValue' => 'job_seeker_service', 'operator' => ['type' => 'string', 'operation' => 'equals']]
                                ]
                            ],
                            'renameOutput' => true,
                            'outputKey' => 'JobSeekerService'
                        ],
                        [
                            'conditions' => [
                                'options' => ['version' => 2],
                                'combinator' => 'or',
                                'conditions' => [
                                    ['leftValue' => '={{ $json.intent }}', 'rightValue' => 'off_topic', 'operator' => ['type' => 'string', 'operation' => 'equals']],
                                    ['leftValue' => '={{ $json.intent }}', 'rightValue' => 'manipulation', 'operator' => ['type' => 'string', 'operation' => 'equals']]
                                ]
                            ],
                            'renameOutput' => true,
                            'outputKey' => 'Safety'
                        ],
                        [
                            'conditions' => [
                                'options' => ['version' => 2],
                                'combinator' => 'and',
                                'conditions' => [
                                    ['leftValue' => '={{ $json.intent }}', 'rightValue' => 'booking', 'operator' => ['type' => 'string', 'operation' => 'equals']]
                                ]
                            ],
                            'renameOutput' => true,
                            'outputKey' => 'Booking'
                        ],
                        [
                            'conditions' => [
                                'options' => ['version' => 2],
                                'combinator' => 'and',
                                'conditions' => [
                                    ['leftValue' => '={{ $json.intent }}', 'rightValue' => 'services', 'operator' => ['type' => 'string', 'operation' => 'equals']]
                                ]
                            ],
                            'renameOutput' => true,
                            'outputKey' => 'Services'
                        ]
                    ]
                ],
                'fallbackOutput' => 'extra',
                'options' => []
            ],
            'type' => 'n8n-nodes-base.switch',
            'typeVersion' => 3.2,
            'position' => [880, 300],
            'id' => 'router',
            'name' => 'Router'
        ],

        // ============ HANDLERS ============

        // 6. Job Application Handler
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const ctx = $("Classify Intent").first().json;
return [{
  json: {
    session_id: ctx.session_id,
    response: `Thank you for your interest in working with AbroadWorks! 🎯

For career opportunities, please visit our jobs portal at **jobs.abroadworks.com** where you can see all open positions and submit your application.

Is there anything else I can help you with regarding our services?`,
    session_update: { is_job_seeker: true },
    intent: "job_application"
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 0],
            'id' => 'job-handler',
            'name' => 'Job Handler'
        ],

        // 7. Job Seeker Service Handler
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const ctx = $("Classify Intent").first().json;
return [{
  json: {
    session_id: ctx.session_id,
    response: `I appreciate your interest in our services! However, to ensure the best experience for everyone, I'd recommend reaching out to our team directly.

📧 **Email:** info@abroadworks.com

They'll be happy to assist you with any service inquiries. Have a great day!`,
    session_update: { status: "completed" },
    intent: "job_seeker_service"
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 150],
            'id' => 'job-seeker-service',
            'name' => 'Job Seeker Service'
        ],

        // 8. Safety Handler
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const ctx = $("Classify Intent").first().json;
const attempts = (ctx.off_topic_attempts || 0) + 1;

let response;
if (attempts >= 2) {
  response = `It seems we're having trouble communicating. Would you like to speak with a human representative?

📧 **Email:** info@abroadworks.com
📞 We're available Monday-Friday, 10AM-6PM EST.

I'm here if you'd like to ask about our services!`;
} else if (ctx.intent === "manipulation") {
  response = `I'm here to help with questions about AbroadWorks services. I can tell you about our Virtual Assistant, Staffing, and Recruitment solutions.

How can I assist you today?`;
} else {
  response = `I'm focused on helping with AbroadWorks services. I can assist you with:

• **Virtual Assistants** - Remote professionals for your business
• **Staffing Solutions** - Dedicated team members
• **Recruitment** - End-to-end hiring support

What would you like to know more about?`;
}

return [{
  json: {
    session_id: ctx.session_id,
    response: response,
    session_update: { off_topic_attempts: attempts },
    intent: ctx.intent
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 300],
            'id' => 'safety-handler',
            'name' => 'Safety Handler'
        ],

        // 9. Booking Handler
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const ctx = $("Classify Intent").first().json;

const response = `Great! I'd be happy to help you schedule a consultation. 📅

To set up your meeting, I'll need a few details:
• Your full name
• Email address
• Phone number
• Company name
• Which service interests you? (Virtual Assistant, Staffing, or Recruitment)

Let's start - **what's your full name?**`;

return [{
  json: {
    session_id: ctx.session_id,
    response: response,
    session_update: { primary_intent: "booking" },
    intent: "booking"
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 450],
            'id' => 'booking-handler',
            'name' => 'Booking Handler'
        ],

        // 10. Services - Prepare Knowledge
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => $knowledgeBase . <<<'JS'

const ctx = $("Classify Intent").first().json;
const topic = ctx.topic || "general";

let knowledge = "";
if (topic === "va") {
  knowledge = KNOWLEDGE.va.name + "\n" + KNOWLEDGE.va.details;
} else if (topic === "staffing") {
  knowledge = KNOWLEDGE.staffing.name + "\n" + KNOWLEDGE.staffing.details;
} else if (topic === "recruitment") {
  knowledge = KNOWLEDGE.recruitment.name + "\n" + KNOWLEDGE.recruitment.details;
} else if (topic === "pricing") {
  knowledge = "PRICING INFO:\n" + KNOWLEDGE.va.details + "\n\n" + KNOWLEDGE.staffing.details + "\n\n" + KNOWLEDGE.recruitment.details;
} else {
  knowledge = "VA: " + KNOWLEDGE.va.details + "\n\nStaffing: " + KNOWLEDGE.staffing.details + "\n\nRecruitment: " + KNOWLEDGE.recruitment.details;
}

return [{
  json: {
    session_id: ctx.session_id,
    message: ctx.message,
    topic: topic,
    knowledge: knowledge,
    intent: "services"
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 600],
            'id' => 'services-prepare',
            'name' => 'Services Prepare'
        ],

        // 11. Services AI
        [
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    'values' => [
                        [
                            'role' => 'system',
                            'content' => "You are AbroadWorks customer service assistant. Be helpful, professional, and concise (under 150 words).\n\nKNOWLEDGE:\n={{ \$json.knowledge }}\n\nRULES:\n- Only answer based on provided knowledge\n- Never make up information\n- Encourage booking a consultation for detailed quotes"
                        ],
                        [
                            'role' => 'user',
                            'content' => '={{ $json.message }}'
                        ]
                    ]
                ],
                'options' => [
                    'temperature' => 0.7,
                    'maxTokens' => 300
                ]
            ],
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'typeVersion' => 1,
            'position' => [1320, 600],
            'id' => 'services-ai',
            'name' => 'Services AI',
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 12. Services Format Response
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const prep = $("Services Prepare").first().json;
const ai = $("Services AI").first().json;

return [{
  json: {
    session_id: prep.session_id,
    response: ai.message?.content || ai.text || "I can help you with our services. What would you like to know?",
    session_update: { primary_intent: "services" },
    intent: "services"
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1540, 600],
            'id' => 'services-format',
            'name' => 'Services Format'
        ],

        // 13. General - Prepare
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => $knowledgeBase . <<<'JS'

const ctx = $("Classify Intent").first().json;

const knowledge = `ABOUT ABROADWORKS:
${KNOWLEDGE.company.about}

CONTACT:
${KNOWLEDGE.company.contact}

SERVICES:
1. Virtual Assistants: ${KNOWLEDGE.va.details}
2. Staffing: ${KNOWLEDGE.staffing.details}
3. Recruitment: ${KNOWLEDGE.recruitment.details}`;

return [{
  json: {
    session_id: ctx.session_id,
    message: ctx.message,
    knowledge: knowledge,
    intent: "general"
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1100, 750],
            'id' => 'general-prepare',
            'name' => 'General Prepare'
        ],

        // 14. General AI
        [
            'parameters' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    'values' => [
                        [
                            'role' => 'system',
                            'content' => "You are AbroadWorks customer service assistant. Be helpful, friendly, and concise.\n\nKNOWLEDGE:\n={{ \$json.knowledge }}\n\nRULES:\n- Only answer based on provided knowledge\n- For greetings, be friendly and offer to help\n- Guide users to specific services"
                        ],
                        [
                            'role' => 'user',
                            'content' => '={{ $json.message }}'
                        ]
                    ]
                ],
                'options' => [
                    'temperature' => 0.7,
                    'maxTokens' => 300
                ]
            ],
            'type' => '@n8n/n8n-nodes-langchain.lmChatOpenAi',
            'typeVersion' => 1,
            'position' => [1320, 750],
            'id' => 'general-ai',
            'name' => 'General AI',
            'credentials' => [
                'openAiApi' => [
                    'id' => 'wKhHW11t6CUkrfq4',
                    'name' => 'OpenAi account'
                ]
            ]
        ],

        // 15. General Format
        [
            'parameters' => [
                'mode' => 'runOnceForAllItems',
                'jsCode' => <<<'JS'
const prep = $("General Prepare").first().json;
const ai = $("General AI").first().json;

return [{
  json: {
    session_id: prep.session_id,
    response: ai.message?.content || ai.text || "Hello! How can I help you today?",
    session_update: {},
    intent: "general"
  }
}];
JS
            ],
            'type' => 'n8n-nodes-base.code',
            'typeVersion' => 2,
            'position' => [1540, 750],
            'id' => 'general-format',
            'name' => 'General Format'
        ],

        // ============ FINAL NODES (connected from all handlers) ============

        // 16. Save Bot Response
        [
            'parameters' => [
                'url' => $messageUrl,
                'method' => 'POST',
                'sendHeaders' => true,
                'headerParameters' => [
                    'parameters' => [
                        ['name' => 'X-Chat-API-Key', 'value' => $chatApiKey]
                    ]
                ],
                'sendBody' => true,
                'specifyBody' => 'json',
                'jsonBody' => '={{ JSON.stringify({ session_id: $json.session_id, role: "assistant", content: $json.response }) }}'
            ],
            'type' => 'n8n-nodes-base.httpRequest',
            'typeVersion' => 4.2,
            'position' => [1760, 400],
            'id' => 'save-bot',
            'name' => 'Save Bot Response'
        ],

        // 17. Respond to Widget
        [
            'parameters' => [
                'respondWith' => 'json',
                'responseBody' => '={{ JSON.stringify({ success: true, response: $json.response, session_id: $json.session_id, intent: $json.intent, session_info: $json.session_update || {} }) }}'
            ],
            'type' => 'n8n-nodes-base.respondToWebhook',
            'typeVersion' => 1.1,
            'position' => [1980, 400],
            'id' => 'respond',
            'name' => 'Respond'
        ]
    ],
    'connections' => [
        'Chat Webhook' => [
            'main' => [[['node' => 'Get Session', 'type' => 'main', 'index' => 0]]]
        ],
        'Get Session' => [
            'main' => [[['node' => 'Save User Message', 'type' => 'main', 'index' => 0]]]
        ],
        'Save User Message' => [
            'main' => [[['node' => 'Classify Intent', 'type' => 'main', 'index' => 0]]]
        ],
        'Classify Intent' => [
            'main' => [[['node' => 'Router', 'type' => 'main', 'index' => 0]]]
        ],
        'Router' => [
            'main' => [
                [['node' => 'Job Handler', 'type' => 'main', 'index' => 0]],           // Job
                [['node' => 'Job Seeker Service', 'type' => 'main', 'index' => 0]],    // JobSeekerService
                [['node' => 'Safety Handler', 'type' => 'main', 'index' => 0]],        // Safety
                [['node' => 'Booking Handler', 'type' => 'main', 'index' => 0]],       // Booking
                [['node' => 'Services Prepare', 'type' => 'main', 'index' => 0]],      // Services
                [['node' => 'General Prepare', 'type' => 'main', 'index' => 0]]        // Fallback (General)
            ]
        ],
        // All handlers connect to Save Bot Response
        'Job Handler' => [
            'main' => [[['node' => 'Save Bot Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Job Seeker Service' => [
            'main' => [[['node' => 'Save Bot Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Safety Handler' => [
            'main' => [[['node' => 'Save Bot Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Booking Handler' => [
            'main' => [[['node' => 'Save Bot Response', 'type' => 'main', 'index' => 0]]]
        ],
        'Services Prepare' => [
            'main' => [[['node' => 'Services AI', 'type' => 'main', 'index' => 0]]]
        ],
        'Services AI' => [
            'main' => [[['node' => 'Services Format', 'type' => 'main', 'index' => 0]]]
        ],
        'Services Format' => [
            'main' => [[['node' => 'Save Bot Response', 'type' => 'main', 'index' => 0]]]
        ],
        'General Prepare' => [
            'main' => [[['node' => 'General AI', 'type' => 'main', 'index' => 0]]]
        ],
        'General AI' => [
            'main' => [[['node' => 'General Format', 'type' => 'main', 'index' => 0]]]
        ],
        'General Format' => [
            'main' => [[['node' => 'Save Bot Response', 'type' => 'main', 'index' => 0]]]
        ],
        // Save Bot Response to Respond
        'Save Bot Response' => [
            'main' => [[['node' => 'Respond', 'type' => 'main', 'index' => 0]]]
        ]
    ],
    'settings' => [
        'executionOrder' => 'v1'
    ]
];

echo "Creating Workflow v3 (No Merge)...\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $n8nHost . '/api/v1/workflows',
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
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300) {
    echo "✅ Workflow created!\n";
    echo "ID: " . ($result['id'] ?? 'unknown') . "\n";
    echo "Webhook: {$n8nHost}/webhook/aw-chat-v3\n\n";

    // Update widget settings with new webhook URL
    $newWebhookUrl = $n8nHost . '/webhook/aw-chat-v3';
    $stmt = $db->prepare("UPDATE n8n_chatbot_settings SET setting_value = ? WHERE setting_key = 'webhook_url'");
    $stmt->execute([$newWebhookUrl]);
    echo "✅ Widget settings updated with new webhook URL\n";

    echo "\n⚠️  IMPORTANT: Go to n8n and ACTIVATE the workflow!\n";
} else {
    echo "❌ Error: $response\n";
}
