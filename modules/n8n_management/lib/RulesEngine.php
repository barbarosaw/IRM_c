<?php
/**
 * Conversation Rules Engine
 * Evaluates rules against conversation context and generates AI instructions
 */

class RulesEngine
{
    private $db;
    private $rules = [];
    private $triggeredRules = [];
    private $pendingActions = [];
    private $aiInstructions = [];

    public function __construct($db)
    {
        $this->db = $db;
        $this->loadRules();
    }

    /**
     * Load all active rules from database
     */
    private function loadRules()
    {
        $stmt = $this->db->query("
            SELECT * FROM conversation_rules
            WHERE is_active = 1
            ORDER BY priority DESC
        ");
        $this->rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Evaluate all rules against given context
     */
    public function evaluate(array $context): array
    {
        $this->triggeredRules = [];
        $this->pendingActions = [];
        $this->aiInstructions = [];

        foreach ($this->rules as $rule) {
            if ($this->evaluateConditions($rule, $context)) {
                $this->triggeredRules[] = [
                    'code' => $rule['rule_code'],
                    'name' => $rule['rule_name'],
                    'category' => $rule['category'],
                    'priority' => $rule['priority']
                ];

                // Execute actions
                $this->executeActions($rule, $context);

                // Add AI instructions
                if (!empty($rule['ai_instructions'])) {
                    $this->aiInstructions[] = $this->interpolate($rule['ai_instructions'], $context);
                }

                // Stop processing if rule says so
                if ($rule['stop_processing']) {
                    break;
                }
            }
        }

        return [
            'triggered_rules' => $this->triggeredRules,
            'pending_actions' => $this->prioritizePendingActions(),
            'ai_instructions' => $this->buildAIInstructions($context)
        ];
    }

    /**
     * Evaluate if all conditions of a rule are met
     */
    private function evaluateConditions(array $rule, array $context): bool
    {
        $conditions = json_decode($rule['conditions'], true);

        // Empty conditions = always true (fallback rules)
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition(array $condition, array $context): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $expectedValue = $condition['value'] ?? null;

        $actualValue = $this->getNestedValue($context, $field);

        switch ($operator) {
            case 'equals':
                return $actualValue === $expectedValue;

            case 'not_equals':
                return $actualValue !== $expectedValue;

            case 'is_empty':
                return empty($actualValue);

            case 'is_not_empty':
                return !empty($actualValue);

            case 'contains':
                if (is_string($actualValue)) {
                    return stripos($actualValue, $expectedValue) !== false;
                }
                return false;

            case 'not_contains':
                if (is_string($actualValue)) {
                    return stripos($actualValue, $expectedValue) === false;
                }
                return true;

            case 'greater_than':
                return is_numeric($actualValue) && $actualValue > $expectedValue;

            case 'less_than':
                return is_numeric($actualValue) && $actualValue < $expectedValue;

            case 'in_array':
                return is_array($expectedValue) && in_array($actualValue, $expectedValue);

            case 'not_in_array':
                return is_array($expectedValue) && !in_array($actualValue, $expectedValue);

            default:
                return false;
        }
    }

    /**
     * Get nested value from context using dot notation
     */
    private function getNestedValue(array $context, string $field)
    {
        $keys = explode('.', $field);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Execute rule actions
     */
    private function executeActions(array $rule, array &$context)
    {
        $actions = json_decode($rule['actions'], true);

        foreach ($actions as $action) {
            $this->executeAction($action, $context);
        }
    }

    /**
     * Execute a single action
     */
    private function executeAction(array $action, array &$context)
    {
        $type = $action['type'];
        $params = $action['params'] ?? [];

        switch ($type) {
            case 'set_pending_action':
                $this->pendingActions[] = [
                    'action' => $params['action'],
                    'priority' => $params['priority'] ?? 50,
                    'params' => $params
                ];
                break;

            case 'update_state':
                $context['new_state'] = $params['state'];
                break;

            case 'set_engagement':
                $context['new_engagement'] = $params['level'];
                break;

            case 'set_booking_intent':
                if (!isset($context['booking_updates'])) {
                    $context['booking_updates'] = [];
                }
                $context['booking_updates'] = array_merge($context['booking_updates'], $params);
                break;

            case 'set_topic_discussed':
                if (!isset($context['topic_updates'])) {
                    $context['topic_updates'] = [];
                }
                $topic = $params['topic'];
                unset($params['topic']);
                $context['topic_updates'][$topic] = $params;
                break;

            case 'set_lead_field':
                if (!isset($context['lead_updates'])) {
                    $context['lead_updates'] = [];
                }
                $context['lead_updates'][$params['field']] = $params['value'];
                break;

            case 'add_ai_instruction':
                $this->aiInstructions[] = $params['instruction'];
                break;

            case 'increment_counter':
                $context['increment_' . $params['counter']] = true;
                break;
        }
    }

    /**
     * Sort pending actions by priority
     */
    private function prioritizePendingActions(): array
    {
        usort($this->pendingActions, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $this->pendingActions;
    }

    /**
     * Build combined AI instructions
     */
    private function buildAIInstructions(array $context): string
    {
        if (empty($this->aiInstructions)) {
            return '';
        }

        $instructions = array_unique($this->aiInstructions);

        // Build structured instructions
        $output = "=== ACTIVE RULES & INSTRUCTIONS ===\n\n";

        foreach ($instructions as $i => $instruction) {
            $output .= ($i + 1) . ". " . $instruction . "\n\n";
        }

        // Add pending actions summary
        if (!empty($this->pendingActions)) {
            $output .= "=== PENDING ACTIONS (priority order) ===\n";
            foreach ($this->pendingActions as $action) {
                $output .= "- {$action['action']}";
                if (!empty($action['params']['field'])) {
                    $output .= " ({$action['params']['field']})";
                }
                $output .= "\n";
            }
        }

        return $output;
    }

    /**
     * Interpolate context values into string
     */
    private function interpolate(string $text, array $context): string
    {
        return preg_replace_callback('/\[([^\]]+)\]/', function($matches) use ($context) {
            $value = $this->getNestedValue($context, $matches[1]);
            return $value ?? $matches[0];
        }, $text);
    }

    /**
     * Get all rules for management UI
     */
    public static function getAllRules($db): array
    {
        $stmt = $db->query("
            SELECT * FROM conversation_rules
            ORDER BY category, priority DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update a rule
     */
    public static function updateRule($db, int $id, array $data): bool
    {
        $allowedFields = ['rule_name', 'category', 'priority', 'conditions', 'actions', 'ai_instructions', 'is_active', 'stop_processing', 'description'];

        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE conversation_rules SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }
}
