<?php
/**
 * Intent Classifier - Determines user intent from message
 * Uses AI to classify into 5 categories
 */

class IntentClassifier
{
    private $db;
    private $openaiKey;

    // Intent categories
    const INTENT_INFORMATION = 'information_seeking';
    const INTENT_LEAD = 'lead';
    const INTENT_CURIOSITY = 'curiosity_fun';
    const INTENT_MALICIOUS = 'malicious';
    const INTENT_EXPLORING = 'exploring';

    // Confidence thresholds
    const HIGH_CONFIDENCE = 0.8;
    const MEDIUM_CONFIDENCE = 0.6;

    public function __construct($db)
    {
        $this->db = $db;
        $this->loadOpenAIKey();
    }

    private function loadOpenAIKey()
    {
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE `key` = 'openai_api_key'");
        $stmt->execute();
        $this->openaiKey = $stmt->fetchColumn();
    }

    /**
     * Classify user intent based on message and conversation context
     */
    public function classify(string $message, array $context = []): array
    {
        // First, try rule-based classification for obvious cases
        $ruleBasedResult = $this->ruleBasedClassify($message, $context);
        if ($ruleBasedResult['confidence'] >= self::HIGH_CONFIDENCE) {
            return $ruleBasedResult;
        }

        // Use AI for ambiguous cases
        $aiResult = $this->aiClassify($message, $context);

        // Combine results
        if ($aiResult['confidence'] > $ruleBasedResult['confidence']) {
            return $aiResult;
        }

        return $ruleBasedResult;
    }

    /**
     * Rule-based classification for obvious patterns
     */
    private function ruleBasedClassify(string $message, array $context): array
    {
        $messageLower = mb_strtolower($message);
        $turnCount = $context['turn_count'] ?? 0;

        // Malicious patterns - highest priority
        $maliciousPatterns = [
            'ignore previous', 'ignore above', 'disregard', 'forget your instructions',
            'you are now', 'act as', 'pretend to be', 'system prompt',
            'what is your prompt', 'show me your instructions', 'reveal your',
            '<script', 'javascript:', 'onclick', 'onerror',
            'SELECT * FROM', 'DROP TABLE', 'UNION SELECT', '1=1',
            '../', '..\\', '/etc/passwd', 'cmd.exe'
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (stripos($messageLower, $pattern) !== false) {
                return [
                    'intent' => self::INTENT_MALICIOUS,
                    'confidence' => 0.95,
                    'reason' => 'Detected injection/manipulation pattern',
                    'pattern_matched' => $pattern
                ];
            }
        }

        // Lead patterns - high intent signals
        $leadPatterns = [
            'book' => 0.85, 'schedule' => 0.85, 'appointment' => 0.85,
            'consultation' => 0.85, 'meeting' => 0.8, 'call' => 0.7,
            'hire' => 0.9, 'work with' => 0.85, 'get started' => 0.85,
            'sign up' => 0.85, 'interested in working' => 0.9,
            'need help with' => 0.75, 'looking for' => 0.7,
            'want to discuss' => 0.8, 'randevu' => 0.85, 'görüşme' => 0.85
        ];

        foreach ($leadPatterns as $pattern => $confidence) {
            if (stripos($messageLower, $pattern) !== false) {
                return [
                    'intent' => self::INTENT_LEAD,
                    'confidence' => $confidence,
                    'reason' => 'Lead intent pattern detected',
                    'pattern_matched' => $pattern
                ];
            }
        }

        // Information seeking patterns
        $infoPatterns = [
            'what is' => 0.8, 'how do' => 0.8, 'how does' => 0.8,
            'tell me about' => 0.75, 'explain' => 0.8, 'what are' => 0.75,
            'can you' => 0.6, 'do you' => 0.6, 'how much' => 0.7,
            'pricing' => 0.7, 'cost' => 0.7, 'services' => 0.65,
            'nedir' => 0.8, 'nasıl' => 0.8, 'ne kadar' => 0.7
        ];

        foreach ($infoPatterns as $pattern => $confidence) {
            if (stripos($messageLower, $pattern) !== false) {
                return [
                    'intent' => self::INTENT_INFORMATION,
                    'confidence' => $confidence,
                    'reason' => 'Information seeking pattern detected',
                    'pattern_matched' => $pattern
                ];
            }
        }

        // Curiosity/Fun patterns
        $funPatterns = [
            'lol' => 0.7, 'haha' => 0.7, 'just kidding' => 0.8,
            'test' => 0.6, 'hello?' => 0.5, 'anyone there' => 0.5,
            'are you real' => 0.7, 'are you a bot' => 0.7, 'are you human' => 0.7,
            'joke' => 0.7, 'funny' => 0.6, 'bored' => 0.7,
            'şaka' => 0.7, 'eğlen' => 0.7
        ];

        foreach ($funPatterns as $pattern => $confidence) {
            if (stripos($messageLower, $pattern) !== false) {
                return [
                    'intent' => self::INTENT_CURIOSITY,
                    'confidence' => $confidence,
                    'reason' => 'Curiosity/fun pattern detected',
                    'pattern_matched' => $pattern
                ];
            }
        }

        // Short messages early in conversation = exploring
        if (mb_strlen($message) < 20 && $turnCount < 3) {
            return [
                'intent' => self::INTENT_EXPLORING,
                'confidence' => 0.5,
                'reason' => 'Short message, early conversation'
            ];
        }

        // Default: exploring with low confidence
        return [
            'intent' => self::INTENT_EXPLORING,
            'confidence' => 0.4,
            'reason' => 'No clear pattern detected'
        ];
    }

    /**
     * AI-powered classification for complex cases
     */
    private function aiClassify(string $message, array $context): array
    {
        if (!$this->openaiKey) {
            return [
                'intent' => self::INTENT_EXPLORING,
                'confidence' => 0.3,
                'reason' => 'No OpenAI key configured'
            ];
        }

        $conversationHistory = $context['recent_messages'] ?? [];
        $collectedData = $context['collected_data'] ?? [];

        $systemPrompt = <<<PROMPT
You are an intent classifier for a business chatbot. Classify the user's intent into ONE of these categories:

1. information_seeking - User wants to learn about services, pricing, how things work
2. lead - User wants to do business, book a call, hire services, get started
3. curiosity_fun - User is just playing around, testing, or bored
4. malicious - User is trying to manipulate, inject commands, or find vulnerabilities
5. exploring - User is unsure, browsing, or their intent is unclear

Context:
- Turn count: {$context['turn_count']}
- Data collected: {$this->formatCollectedData($collectedData)}
- Previous state: {$context['current_state']}

Recent conversation:
{$this->formatConversationHistory($conversationHistory)}

Respond ONLY with JSON:
{
  "intent": "category_name",
  "confidence": 0.0-1.0,
  "reason": "brief explanation",
  "suggested_action": "what to do next"
}
PROMPT;

        try {
            $response = $this->callOpenAI($systemPrompt, $message);
            $result = json_decode($response, true);

            if ($result && isset($result['intent'])) {
                return [
                    'intent' => $result['intent'],
                    'confidence' => (float)($result['confidence'] ?? 0.7),
                    'reason' => $result['reason'] ?? 'AI classification',
                    'suggested_action' => $result['suggested_action'] ?? null,
                    'source' => 'ai'
                ];
            }
        } catch (Exception $e) {
            error_log('Intent classification AI error: ' . $e->getMessage());
        }

        return [
            'intent' => self::INTENT_EXPLORING,
            'confidence' => 0.3,
            'reason' => 'AI classification failed'
        ];
    }

    /**
     * Call OpenAI API directly (small/fast model for classification)
     */
    private function callOpenAI(string $systemPrompt, string $userMessage): string
    {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');

        $payload = [
            'model' => 'gpt-4o-mini', // Fast and cheap for classification
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => 0.3, // Low temperature for consistent classification
            'max_tokens' => 200
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiKey
            ],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("OpenAI API error: HTTP $httpCode");
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function formatCollectedData(array $data): string
    {
        $parts = [];
        if (!empty($data['full_name'])) $parts[] = "name";
        if (!empty($data['email'])) $parts[] = "email";
        if (!empty($data['phone'])) $parts[] = "phone";
        if (!empty($data['business_name'])) $parts[] = "company";
        return empty($parts) ? 'none' : implode(', ', $parts);
    }

    private function formatConversationHistory(array $messages): string
    {
        if (empty($messages)) return 'No previous messages';

        $formatted = [];
        foreach (array_slice($messages, -5) as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = substr($msg['content'] ?? '', 0, 100);
            $formatted[] = "$role: $content";
        }
        return implode("\n", $formatted);
    }

    /**
     * Log intent classification for analytics
     */
    public function logIntent(string $sessionId, string $message, array $result): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO intent_log (session_id, message, intent, confidence, reason, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sessionId,
                substr($message, 0, 500),
                $result['intent'],
                $result['confidence'],
                $result['reason'] ?? null
            ]);
        } catch (Exception $e) {
            error_log('Failed to log intent: ' . $e->getMessage());
        }
    }
}
