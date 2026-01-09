<?php
/**
 * Conversation State Machine
 * Manages conversation flow states and transitions
 */

class StateMachine
{
    private $db;

    // Main conversation states
    const STATE_GREETING = 'greeting';
    const STATE_EXPLORING = 'exploring';
    const STATE_INFO_GATHERING = 'info_gathering';
    const STATE_LEAD_QUALIFICATION = 'lead_qualification';
    const STATE_DATA_COLLECTION = 'data_collection';
    const STATE_BOOKING_INTENT = 'booking_intent';
    const STATE_BOOKING_TIME = 'booking_time';
    const STATE_BOOKING_CONFIRM = 'booking_confirm';
    const STATE_CLOSING = 'closing';
    const STATE_COMPLETED = 'completed';
    const STATE_BLOCKED = 'blocked';

    // Data collection priority (what to collect first)
    const DATA_PRIORITY = [
        'full_name' => 1,
        'email' => 2,
        'phone' => 3,
        'business_name' => 4,
        'position_needed' => 5,
        'industry' => 6,
        'company_size' => 7,
        'location' => 8,
        'timezone' => 9
    ];

    // Minimum required fields for booking
    const BOOKING_REQUIRED = ['full_name', 'email', 'phone'];

    // State definitions with allowed transitions
    private $stateDefinitions = [
        'greeting' => [
            'description' => 'Initial greeting, first contact',
            'allowed_transitions' => ['exploring', 'info_gathering', 'lead_qualification', 'blocked'],
            'max_turns' => 2,
            'tone' => 'warm, welcoming',
            'goal' => 'Understand what user is looking for'
        ],
        'exploring' => [
            'description' => 'User is browsing, unclear intent',
            'allowed_transitions' => ['info_gathering', 'lead_qualification', 'closing', 'blocked'],
            'max_turns' => 5,
            'tone' => 'helpful, guiding',
            'goal' => 'Guide user to a specific path'
        ],
        'info_gathering' => [
            'description' => 'User wants information about services',
            'allowed_transitions' => ['lead_qualification', 'data_collection', 'closing', 'blocked'],
            'max_turns' => 10,
            'tone' => 'informative, concise',
            'goal' => 'Answer questions, collect basic data opportunistically'
        ],
        'lead_qualification' => [
            'description' => 'Determining if user is a potential lead',
            'allowed_transitions' => ['data_collection', 'booking_intent', 'closing', 'blocked'],
            'max_turns' => 5,
            'tone' => 'professional, interested',
            'goal' => 'Assess business potential'
        ],
        'data_collection' => [
            'description' => 'Collecting required information',
            'allowed_transitions' => ['booking_intent', 'booking_time', 'closing'],
            'max_turns' => 8,
            'tone' => 'efficient, one question at a time',
            'goal' => 'Collect minimum required fields naturally'
        ],
        'booking_intent' => [
            'description' => 'Confirming user wants to book',
            'allowed_transitions' => ['booking_time', 'data_collection', 'closing'],
            'max_turns' => 3,
            'tone' => 'confirming, helpful',
            'goal' => 'Confirm booking intent, check missing data'
        ],
        'booking_time' => [
            'description' => 'Collecting time preference',
            'allowed_transitions' => ['booking_confirm', 'data_collection'],
            'max_turns' => 5,
            'tone' => 'efficient, offering options',
            'goal' => 'Get time preference, offer 3 slots'
        ],
        'booking_confirm' => [
            'description' => 'Confirming booking details',
            'allowed_transitions' => ['completed', 'booking_time'],
            'max_turns' => 3,
            'tone' => 'confirming, clear',
            'goal' => 'Confirm all details, create booking'
        ],
        'closing' => [
            'description' => 'Ending conversation gracefully',
            'allowed_transitions' => ['completed', 'lead_qualification'],
            'max_turns' => 3,
            'tone' => 'friendly, open for future',
            'goal' => 'End conversation positively'
        ],
        'completed' => [
            'description' => 'Conversation ended',
            'allowed_transitions' => [],
            'max_turns' => 0,
            'tone' => 'final',
            'goal' => 'Done'
        ],
        'blocked' => [
            'description' => 'User blocked for malicious behavior',
            'allowed_transitions' => [],
            'max_turns' => 0,
            'tone' => 'firm',
            'goal' => 'End interaction'
        ]
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get current state definition
     */
    public function getStateDefinition(string $state): array
    {
        return $this->stateDefinitions[$state] ?? $this->stateDefinitions['greeting'];
    }

    /**
     * Determine next state based on intent and context
     */
    public function determineNextState(string $currentState, string $intent, array $context): array
    {
        $stateDef = $this->getStateDefinition($currentState);
        $turnCount = $context['turn_count'] ?? 0;
        $statesTurnCount = $context['state_turn_count'] ?? 0;
        $collectedData = $context['collected_data'] ?? [];

        // Check for malicious intent - immediate block
        if ($intent === 'malicious') {
            return [
                'next_state' => self::STATE_BLOCKED,
                'reason' => 'Malicious intent detected',
                'action' => 'block_user'
            ];
        }

        // State-specific logic
        switch ($currentState) {
            case self::STATE_GREETING:
                return $this->handleGreetingTransition($intent, $context);

            case self::STATE_EXPLORING:
                return $this->handleExploringTransition($intent, $context);

            case self::STATE_INFO_GATHERING:
                return $this->handleInfoGatheringTransition($intent, $context);

            case self::STATE_LEAD_QUALIFICATION:
                return $this->handleLeadQualificationTransition($intent, $context);

            case self::STATE_DATA_COLLECTION:
                return $this->handleDataCollectionTransition($intent, $context, $collectedData);

            case self::STATE_BOOKING_INTENT:
                return $this->handleBookingIntentTransition($intent, $context, $collectedData);

            case self::STATE_BOOKING_TIME:
                return $this->handleBookingTimeTransition($intent, $context);

            case self::STATE_BOOKING_CONFIRM:
                return $this->handleBookingConfirmTransition($intent, $context);

            case self::STATE_CLOSING:
                return $this->handleClosingTransition($intent, $context);

            default:
                return [
                    'next_state' => $currentState,
                    'reason' => 'No transition needed'
                ];
        }
    }

    private function handleGreetingTransition(string $intent, array $context): array
    {
        switch ($intent) {
            case 'lead':
                return [
                    'next_state' => self::STATE_LEAD_QUALIFICATION,
                    'reason' => 'User showed lead intent from start',
                    'action' => 'assess_lead_potential'
                ];
            case 'information_seeking':
                return [
                    'next_state' => self::STATE_INFO_GATHERING,
                    'reason' => 'User wants information',
                    'action' => 'provide_info'
                ];
            case 'curiosity_fun':
                return [
                    'next_state' => self::STATE_CLOSING,
                    'reason' => 'User appears to be just browsing',
                    'action' => 'polite_redirect'
                ];
            default:
                return [
                    'next_state' => self::STATE_EXPLORING,
                    'reason' => 'Unclear intent, need to explore',
                    'action' => 'ask_clarifying_question'
                ];
        }
    }

    private function handleExploringTransition(string $intent, array $context): array
    {
        $turnCount = $context['state_turn_count'] ?? 0;

        // If stuck in exploring too long, try to close
        if ($turnCount > 4) {
            return [
                'next_state' => self::STATE_CLOSING,
                'reason' => 'Too long in exploring state',
                'action' => 'offer_help_or_close'
            ];
        }

        switch ($intent) {
            case 'lead':
                return [
                    'next_state' => self::STATE_LEAD_QUALIFICATION,
                    'reason' => 'User showed lead intent',
                    'action' => 'assess_lead_potential'
                ];
            case 'information_seeking':
                return [
                    'next_state' => self::STATE_INFO_GATHERING,
                    'reason' => 'User wants specific information',
                    'action' => 'provide_info'
                ];
            case 'curiosity_fun':
                return [
                    'next_state' => self::STATE_CLOSING,
                    'reason' => 'User not serious',
                    'action' => 'polite_close'
                ];
            default:
                return [
                    'next_state' => self::STATE_EXPLORING,
                    'reason' => 'Still exploring',
                    'action' => 'guide_to_topic'
                ];
        }
    }

    private function handleInfoGatheringTransition(string $intent, array $context): array
    {
        $questionsAnswered = $context['questions_answered'] ?? 0;

        // After answering several questions, suggest lead path
        if ($questionsAnswered >= 3 && $intent !== 'curiosity_fun') {
            return [
                'next_state' => self::STATE_LEAD_QUALIFICATION,
                'reason' => 'User engaged, time to qualify',
                'action' => 'suggest_consultation'
            ];
        }

        if ($intent === 'lead') {
            return [
                'next_state' => self::STATE_LEAD_QUALIFICATION,
                'reason' => 'User showed lead intent',
                'action' => 'assess_lead_potential'
            ];
        }

        return [
            'next_state' => self::STATE_INFO_GATHERING,
            'reason' => 'Continue providing information',
            'action' => 'answer_and_collect_one_data'
        ];
    }

    private function handleLeadQualificationTransition(string $intent, array $context): array
    {
        $leadScore = $context['lead_score'] ?? 0;

        if ($intent === 'lead' || $leadScore > 60) {
            return [
                'next_state' => self::STATE_DATA_COLLECTION,
                'reason' => 'User qualifies as lead',
                'action' => 'start_data_collection'
            ];
        }

        if ($intent === 'curiosity_fun') {
            return [
                'next_state' => self::STATE_CLOSING,
                'reason' => 'User not a serious lead',
                'action' => 'polite_close'
            ];
        }

        return [
            'next_state' => self::STATE_LEAD_QUALIFICATION,
            'reason' => 'Still qualifying',
            'action' => 'ask_qualifying_question'
        ];
    }

    private function handleDataCollectionTransition(string $intent, array $context, array $collectedData): array
    {
        $missingRequired = $this->getMissingRequiredFields($collectedData);

        // All required data collected
        if (empty($missingRequired)) {
            return [
                'next_state' => self::STATE_BOOKING_INTENT,
                'reason' => 'All required data collected',
                'action' => 'confirm_booking_intent'
            ];
        }

        // User wants to book but missing data
        if ($intent === 'lead' && count($missingRequired) <= 2) {
            return [
                'next_state' => self::STATE_DATA_COLLECTION,
                'reason' => 'Almost ready, collecting final fields',
                'action' => 'collect_' . $missingRequired[0]
            ];
        }

        return [
            'next_state' => self::STATE_DATA_COLLECTION,
            'reason' => 'Still collecting data',
            'action' => 'collect_' . $missingRequired[0]
        ];
    }

    private function handleBookingIntentTransition(string $intent, array $context, array $collectedData): array
    {
        $bookingConfirmed = $context['booking_confirmed'] ?? false;

        if ($bookingConfirmed || $intent === 'lead') {
            $missingRequired = $this->getMissingRequiredFields($collectedData);
            if (!empty($missingRequired)) {
                return [
                    'next_state' => self::STATE_DATA_COLLECTION,
                    'reason' => 'Missing required data for booking',
                    'action' => 'collect_' . $missingRequired[0]
                ];
            }

            return [
                'next_state' => self::STATE_BOOKING_TIME,
                'reason' => 'Booking confirmed, need time',
                'action' => 'ask_preferred_time'
            ];
        }

        if ($intent === 'curiosity_fun' || $intent === 'exploring') {
            return [
                'next_state' => self::STATE_CLOSING,
                'reason' => 'User not interested in booking',
                'action' => 'offer_alternatives'
            ];
        }

        return [
            'next_state' => self::STATE_BOOKING_INTENT,
            'reason' => 'Clarifying booking intent',
            'action' => 'confirm_booking_interest'
        ];
    }

    private function handleBookingTimeTransition(string $intent, array $context): array
    {
        $timeProvided = !empty($context['booking_time']);
        $slotsOffered = $context['slots_offered'] ?? false;

        if ($timeProvided) {
            return [
                'next_state' => self::STATE_BOOKING_CONFIRM,
                'reason' => 'Time provided',
                'action' => 'confirm_booking_details'
            ];
        }

        return [
            'next_state' => self::STATE_BOOKING_TIME,
            'reason' => 'Waiting for time selection',
            'action' => $slotsOffered ? 'wait_for_selection' : 'offer_time_slots'
        ];
    }

    private function handleBookingConfirmTransition(string $intent, array $context): array
    {
        $confirmed = $context['final_confirmed'] ?? false;

        if ($confirmed) {
            return [
                'next_state' => self::STATE_COMPLETED,
                'reason' => 'Booking completed',
                'action' => 'send_confirmations'
            ];
        }

        return [
            'next_state' => self::STATE_BOOKING_CONFIRM,
            'reason' => 'Waiting for confirmation',
            'action' => 'show_summary_and_confirm'
        ];
    }

    private function handleClosingTransition(string $intent, array $context): array
    {
        // Last chance to convert
        if ($intent === 'lead') {
            return [
                'next_state' => self::STATE_LEAD_QUALIFICATION,
                'reason' => 'User changed mind',
                'action' => 'welcome_back'
            ];
        }

        return [
            'next_state' => self::STATE_COMPLETED,
            'reason' => 'Conversation ending',
            'action' => 'say_goodbye'
        ];
    }

    /**
     * Get missing required fields for booking
     */
    public function getMissingRequiredFields(array $collectedData): array
    {
        $missing = [];
        foreach (self::BOOKING_REQUIRED as $field) {
            if (empty($collectedData[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Get next field to collect based on priority
     */
    public function getNextFieldToCollect(array $collectedData): ?string
    {
        $sorted = self::DATA_PRIORITY;
        asort($sorted);

        foreach ($sorted as $field => $priority) {
            if (empty($collectedData[$field])) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get forbidden phrases for current state
     */
    public function getForbiddenPhrases(string $state, array $collectedData): array
    {
        $forbidden = [];

        // Don't ask for data we already have
        if (!empty($collectedData['full_name'])) {
            $forbidden[] = 'what is your name';
            $forbidden[] = 'may i know your name';
            $forbidden[] = 'adınız nedir';
        }

        if (!empty($collectedData['email'])) {
            $forbidden[] = 'your email';
            $forbidden[] = 'email address';
            $forbidden[] = 'e-posta';
        }

        if (!empty($collectedData['phone'])) {
            $forbidden[] = 'phone number';
            $forbidden[] = 'contact number';
            $forbidden[] = 'telefon';
            $forbidden[] = 'numara';
        }

        if (!empty($collectedData['business_name'])) {
            $forbidden[] = 'company name';
            $forbidden[] = 'business name';
            $forbidden[] = 'which company';
            $forbidden[] = 'şirket';
            $forbidden[] = 'hangi firma';
        }

        // State-specific forbidden
        if ($state === 'info_gathering') {
            $forbidden[] = 'would you like to book';
            $forbidden[] = 'schedule a call';
        }

        if ($state === 'closing') {
            $forbidden[] = 'tell me more';
            $forbidden[] = 'what else';
        }

        return $forbidden;
    }

    /**
     * Calculate lead score based on collected data and engagement
     */
    public function calculateLeadScore(array $collectedData, array $context): int
    {
        $score = 0;

        // Data completeness (max 40 points)
        if (!empty($collectedData['full_name'])) $score += 10;
        if (!empty($collectedData['email'])) $score += 15;
        if (!empty($collectedData['phone'])) $score += 10;
        if (!empty($collectedData['business_name'])) $score += 5;

        // Engagement (max 30 points)
        $turnCount = $context['turn_count'] ?? 0;
        if ($turnCount >= 3) $score += 10;
        if ($turnCount >= 6) $score += 10;
        if ($turnCount >= 10) $score += 10;

        // Intent signals (max 30 points)
        $intent = $context['current_intent'] ?? '';
        if ($intent === 'lead') $score += 30;
        elseif ($intent === 'information_seeking') $score += 15;

        return min(100, $score);
    }
}
