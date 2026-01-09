<?php
/**
 * HubSpot Integration API
 * Contact management and conversation sync for chat widget
 * 
 * POST actions:
 * - search_contact: Search contact by email
 * - create_contact: Create new contact
 * - update_contact: Update existing contact
 * - add_note: Add conversation note to contact
 * - get_company: Get company by domain
 * - create_engagement: Create engagement (conversation)
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Get HubSpot API key from settings
function getHubSpotApiKey($db) {
    $stmt = $db->prepare("SELECT setting_value FROM n8n_chatbot_settings WHERE setting_key = 'hubspot_api_key'");
    $stmt->execute();
    $key = $stmt->fetchColumn();
    if (!$key) {
        // Try environment or config
        $key = getenv('HUBSPOT_API_KEY') ?: null;
    }
    return $key;
}

// HubSpot API call helper
function hubspotRequest($apiKey, $endpoint, $method = 'GET', $data = null) {
    $baseUrl = 'https://api.hubapi.com';
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $decoded = json_decode($response, true);
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $decoded
    ];
}

// Search contact by email
function searchContact($apiKey, $email) {
    $data = [
        'filterGroups' => [[
            'filters' => [[
                'propertyName' => 'email',
                'operator' => 'EQ',
                'value' => $email
            ]]
        ]],
        'properties' => ['email', 'firstname', 'lastname', 'phone', 'company', 'jobtitle', 'hs_lead_status']
    ];
    
    return hubspotRequest($apiKey, '/crm/v3/objects/contacts/search', 'POST', $data);
}

// Create contact
function createContact($apiKey, $properties) {
    $data = ['properties' => $properties];
    return hubspotRequest($apiKey, '/crm/v3/objects/contacts', 'POST', $data);
}

// Update contact
function updateContact($apiKey, $contactId, $properties) {
    $data = ['properties' => $properties];
    return hubspotRequest($apiKey, '/crm/v3/objects/contacts/' . $contactId, 'PATCH', $data);
}

// Add note to contact
function addNote($apiKey, $contactId, $noteBody) {
    // Create note engagement
    $data = [
        'properties' => [
            'hs_timestamp' => (string)(time() * 1000),
            'hs_note_body' => $noteBody
        ],
        'associations' => [[
            'to' => ['id' => $contactId],
            'types' => [[
                'associationCategory' => 'HUBSPOT_DEFINED',
                'associationTypeId' => 202  // Note to Contact
            ]]
        ]]
    ];
    
    return hubspotRequest($apiKey, '/crm/v3/objects/notes', 'POST', $data);
}

// Get company by domain
function getCompanyByDomain($apiKey, $domain) {
    $data = [
        'filterGroups' => [[
            'filters' => [[
                'propertyName' => 'domain',
                'operator' => 'EQ',
                'value' => $domain
            ]]
        ]],
        'properties' => ['name', 'domain', 'industry', 'numberofemployees']
    ];
    
    return hubspotRequest($apiKey, '/crm/v3/objects/companies/search', 'POST', $data);
}

// Sync conversation to HubSpot
function syncConversation($db, $apiKey, $sessionId, $contactId) {
    // Get all messages for session
    $stmt = $db->prepare("
        SELECT role, content, created_at 
        FROM chat_messages 
        WHERE session_id = ? 
        ORDER BY created_at
    ");
    $stmt->execute([$sessionId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        return ['success' => false, 'error' => 'No messages found'];
    }
    
    // Format conversation
    $conversationText = "=== Chat Conversation ===\n";
    $conversationText .= "Session ID: $sessionId\n";
    $conversationText .= "Date: " . $messages[0]['created_at'] . "\n\n";
    
    foreach ($messages as $msg) {
        $role = $msg['role'] === 'user' ? 'Visitor' : 'Bot';
        $conversationText .= "[$role] " . $msg['created_at'] . "\n";
        $conversationText .= $msg['content'] . "\n\n";
    }
    
    // Get lead data
    $stmt = $db->prepare("SELECT * FROM chat_leads WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lead) {
        $conversationText .= "\n=== Lead Information ===\n";
        $conversationText .= "Name: " . ($lead['full_name'] ?? 'N/A') . "\n";
        $conversationText .= "Email: " . ($lead['email'] ?? 'N/A') . "\n";
        $conversationText .= "Phone: " . ($lead['phone'] ?? 'N/A') . "\n";
        $conversationText .= "Intent: " . ($lead['primary_intent'] ?? 'N/A') . "\n";
    }
    
    // Add as note
    return addNote($apiKey, $contactId, $conversationText);
}

// Handle request
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

$apiKey = getHubSpotApiKey($db);
if (!$apiKey) {
    jsonResponse(['success' => false, 'error' => 'HubSpot API key not configured'], 500);
}

switch ($action) {
    case 'search_contact':
        $email = $input['email'] ?? null;
        if (!$email) {
            jsonResponse(['success' => false, 'error' => 'email required'], 400);
        }
        $result = searchContact($apiKey, $email);
        $found = !empty($result['data']['results']);
        jsonResponse([
            'success' => $result['success'],
            'found' => $found,
            'contact' => $found ? $result['data']['results'][0] : null
        ]);
        break;
        
    case 'create_contact':
        $properties = $input['properties'] ?? [];
        if (empty($properties['email'])) {
            jsonResponse(['success' => false, 'error' => 'email property required'], 400);
        }
        $result = createContact($apiKey, $properties);
        jsonResponse($result);
        break;
        
    case 'update_contact':
        $contactId = $input['contact_id'] ?? null;
        $properties = $input['properties'] ?? [];
        if (!$contactId) {
            jsonResponse(['success' => false, 'error' => 'contact_id required'], 400);
        }
        $result = updateContact($apiKey, $contactId, $properties);
        jsonResponse($result);
        break;
        
    case 'sync_or_create':
        // Smart sync: search, create if not exists, then sync conversation
        $sessionId = $input['session_id'] ?? null;
        $email = $input['email'] ?? null;
        $name = $input['name'] ?? null;
        $phone = $input['phone'] ?? null;
        
        if (!$email) {
            jsonResponse(['success' => false, 'error' => 'email required'], 400);
        }
        
        // Search for existing contact
        $searchResult = searchContact($apiKey, $email);
        $contactId = null;
        
        if (!empty($searchResult['data']['results'])) {
            // Contact exists - update if we have new info
            $contactId = $searchResult['data']['results'][0]['id'];
            $existingProps = $searchResult['data']['results'][0]['properties'];
            
            $updateProps = [];
            if ($name && empty($existingProps['firstname'])) {
                $nameParts = explode(' ', $name, 2);
                $updateProps['firstname'] = $nameParts[0];
                if (isset($nameParts[1])) $updateProps['lastname'] = $nameParts[1];
            }
            if ($phone && empty($existingProps['phone'])) {
                $updateProps['phone'] = $phone;
            }
            
            if (!empty($updateProps)) {
                updateContact($apiKey, $contactId, $updateProps);
            }
            
            $action_taken = 'updated';
        } else {
            // Create new contact
            $properties = ['email' => $email];
            if ($name) {
                $nameParts = explode(' ', $name, 2);
                $properties['firstname'] = $nameParts[0];
                if (isset($nameParts[1])) $properties['lastname'] = $nameParts[1];
            }
            if ($phone) {
                $properties['phone'] = $phone;
            }
            $properties['hs_lead_status'] = 'NEW';
            $properties['lifecyclestage'] = 'lead';
            
            $createResult = createContact($apiKey, $properties);
            if (!$createResult['success']) {
                jsonResponse($createResult);
            }
            $contactId = $createResult['data']['id'];
            $action_taken = 'created';
        }
        
        // Sync conversation if session provided
        $syncResult = null;
        if ($sessionId && $contactId) {
            $syncResult = syncConversation($db, $apiKey, $sessionId, $contactId);
        }
        
        jsonResponse([
            'success' => true,
            'contact_id' => $contactId,
            'action' => $action_taken,
            'conversation_synced' => $syncResult ? $syncResult['success'] : false
        ]);
        break;
        
    case 'get_company':
        $domain = $input['domain'] ?? null;
        if (!$domain) {
            jsonResponse(['success' => false, 'error' => 'domain required'], 400);
        }
        $result = getCompanyByDomain($apiKey, $domain);
        $found = !empty($result['data']['results']);
        jsonResponse([
            'success' => $result['success'],
            'found' => $found,
            'company' => $found ? $result['data']['results'][0] : null
        ]);
        break;
        
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action. Valid: search_contact, create_contact, update_contact, sync_or_create, get_company'], 400);
}
