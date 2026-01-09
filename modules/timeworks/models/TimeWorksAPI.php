<?php
/**
 * AbroadWorks Management System - TimeWorks API Helper Class
 *
 * This class handles all communication with TimeWorks API including:
 * - JWT token management (refresh token, access token)
 * - User synchronization
 * - Project management
 * - Time sheet reports
 * - Error handling and logging
 *
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class TimeWorksAPI {
    private $db;
    private $baseUrl = 'https://api.timeworks.abroadworks.com/api/v1/';
    private $organizationId = 2;
    private $refreshToken;
    private $accessToken;
    private $accessTokenExpiry;
    private $loginEmail;
    private $loginPassword;

    /**
     * Constructor
     *
     * @param PDO $connection Database connection
     */
    public function __construct($connection = null) {
        if ($connection) {
            $this->db = $connection;
        } else {
            global $db;
            $this->db = $db;
        }

        // Load refresh token from settings
        $this->loadRefreshToken();

        // Load login credentials from settings
        $this->loadLoginCredentials();
    }

    /**
     * Load refresh token from database settings
     *
     * @return void
     */
    private function loadRefreshToken() {
        try {
            $stmt = $this->db->prepare("SELECT value FROM settings WHERE `key` = 'timeworks_refresh_token' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();

            if ($result && !empty($result['value'])) {
                $this->refreshToken = $result['value'];
            } else {
                // Fallback to hardcoded refresh token
                $this->refreshToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiYXJiYXJvc0BhYnJvYWR3b3Jrcy5jb20iLCJleHAiOjE3NjY0NDUyMTEsInR5cGUiOiJyZWZyZXNoIn0.DyAnvsDtxWMBu4h-E-HIJKxFI0pT1eytHppXwkjNhFk';

                // Try to insert into settings
                try {
                    $stmt = $this->db->prepare("INSERT INTO settings (`key`, `value`, `group`, `description`, `type`, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()");
                    $stmt->execute([
                        'timeworks_refresh_token',
                        $this->refreshToken,
                        'api',
                        'TimeWorks API Refresh Token',
                        'text',
                        $this->refreshToken
                    ]);
                } catch (Exception $e) {
                    error_log("TimeWorksAPI: Could not save refresh token to settings - " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("TimeWorksAPI: Error loading refresh token - " . $e->getMessage());
            // Use hardcoded fallback
            $this->refreshToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJiYXJiYXJvc0BhYnJvYWR3b3Jrcy5jb20iLCJleHAiOjE3NjY0NDUyMTEsInR5cGUiOiJyZWZyZXNoIn0.DyAnvsDtxWMBu4h-E-HIJKxFI0pT1eytHppXwkjNhFk';
        }
    }

    /**
     * Load login credentials from database settings
     *
     * @return void
     */
    private function loadLoginCredentials() {
        try {
            $stmt = $this->db->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('timeworks_email', 'timeworks_password')");
            $stmt->execute();
            $results = $stmt->fetchAll();

            foreach ($results as $row) {
                if ($row['key'] === 'timeworks_email') {
                    $this->loginEmail = $row['value'];
                } elseif ($row['key'] === 'timeworks_password') {
                    $this->loginPassword = $row['value'];
                }
            }
        } catch (Exception $e) {
            error_log("TimeWorksAPI: Error loading login credentials - " . $e->getMessage());
        }
    }

    /**
     * Login with email/password to get new tokens
     *
     * @return bool True if login successful
     */
    private function login() {
        if (empty($this->loginEmail) || empty($this->loginPassword)) {
            error_log("TimeWorksAPI: Login credentials not configured");
            return false;
        }

        $url = $this->baseUrl . 'token';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'email' => $this->loginEmail,
                'password' => $this->loginPassword
            ]),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("TimeWorksAPI: cURL error in login - " . $curlError);
            return false;
        }

        if ($httpCode !== 200) {
            error_log("TimeWorksAPI: HTTP {$httpCode} error in login - Response: " . $response);
            return false;
        }

        $data = json_decode($response, true);

        if (isset($data['access_token']) && isset($data['refresh_token'])) {
            $this->accessToken = $data['access_token'];
            $this->refreshToken = $data['refresh_token'];
            $this->accessTokenExpiry = time() + ($data['expires_in'] ?? 3600);

            // Save new refresh token to database
            $this->saveRefreshToken($data['refresh_token']);

            error_log("TimeWorksAPI: Auto-login successful, tokens refreshed");
            return true;
        }

        error_log("TimeWorksAPI: Invalid login response - " . $response);
        return false;
    }

    /**
     * Save refresh token to database
     *
     * @param string $token Refresh token
     * @return void
     */
    private function saveRefreshToken($token) {
        try {
            $stmt = $this->db->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE `key` = 'timeworks_refresh_token'");
            $stmt->execute([$token]);
        } catch (Exception $e) {
            error_log("TimeWorksAPI: Could not save refresh token - " . $e->getMessage());
        }
    }

    /**
     * Get new access token using refresh token
     *
     * @return string|null Access token or null on failure
     */
    public function getAccessToken() {
        // Check if we have a valid cached access token
        if ($this->accessToken && $this->accessTokenExpiry && time() < $this->accessTokenExpiry - 60) {
            return $this->accessToken;
        }

        $url = $this->baseUrl . 'refresh-token';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->refreshToken
            ],
            CURLOPT_POSTFIELDS => json_encode(['refresh_token' => $this->refreshToken]),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("TimeWorksAPI: cURL error in getAccessToken - " . $curlError);
            return null;
        }

        if ($httpCode !== 200) {
            error_log("TimeWorksAPI: HTTP {$httpCode} error in getAccessToken - Response: " . $response);

            // If refresh token expired (401), try auto-login
            if ($httpCode === 401) {
                error_log("TimeWorksAPI: Refresh token expired, attempting auto-login...");
                if ($this->login()) {
                    return $this->accessToken;
                }
            }
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['access_token'])) {
            $this->accessToken = $data['access_token'];
            $this->accessTokenExpiry = time() + ($data['expires_in'] ?? 3600);

            // If response includes new refresh token, save it
            if (isset($data['refresh_token'])) {
                $this->refreshToken = $data['refresh_token'];
                $this->saveRefreshToken($data['refresh_token']);
            }

            return $this->accessToken;
        }

        error_log("TimeWorksAPI: No access_token in response - " . $response);
        return null;
    }

    /**
     * Make API request with automatic token refresh
     *
     * @param string $endpoint API endpoint (without base URL)
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $data Request data (for POST/PUT)
     * @param array $queryParams Query parameters (for GET)
     * @return array|null Response data or null on failure
     */
    public function makeRequest($endpoint, $method = 'GET', $data = [], $queryParams = []) {
        // Get access token
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        // Build URL
        $url = $this->baseUrl . ltrim($endpoint, '/');
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init($url);

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 60
        ];

        // Set method and data
        switch (strtoupper($method)) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case 'PUT':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                break;
            case 'DELETE':
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            default:
                $curlOptions[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("TimeWorksAPI: cURL error in makeRequest - " . $curlError);
            return null;
        }

        if ($httpCode !== 200) {
            error_log("TimeWorksAPI: HTTP {$httpCode} error - Endpoint: {$endpoint} - Response: " . $response);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Get all users from organization
     *
     * @param int $limit Maximum number of users to fetch
     * @param int $offset Pagination offset
     * @return array|null User list or null on failure
     */
    public function getUsers($limit = 700, $offset = 0) {
        $params = [
            'organization_id' => $this->organizationId,
            'limit' => $limit,
            'offset' => $offset
        ];

        return $this->makeRequest('user/list', 'GET', [], $params);
    }

    /**
     * Get all projects from organization
     *
     * @param int $limit Maximum number of projects to fetch
     * @param int $offset Pagination offset
     * @return array|null Project list or null on failure
     */
    public function getProjects($limit = 600, $offset = 0) {
        $params = [
            'limit' => $limit,
            'offset' => $offset
        ];

        return $this->makeRequest('projects/' . $this->organizationId, 'GET', [], $params);
    }

    /**
     * Get project members
     *
     * @param string $projectId Project ID
     * @param int $limit Maximum number of members to fetch
     * @param int $offset Pagination offset
     * @return array|null Member list or null on failure
     */
    public function getProjectMembers($projectId, $limit = 150, $offset = 0) {
        $params = [
            'limit' => $limit,
            'offset' => $offset
        ];

        return $this->makeRequest("project-members/{$this->organizationId}/{$projectId}", 'GET', [], $params);
    }

    /**
     * Get user time sheet report
     *
     * @param string $userId User ID
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param string $timezone Timezone string (default: Europe/Istanbul)
     * @param int $limit Maximum number of days
     * @param int $offset Pagination offset
     * @return array|null Time sheet report or null on failure
     */
    public function getUserTimeSheet($userId, $startDate, $endDate, $timezone = 'Europe/Istanbul', $limit = 31, $offset = 0) {
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'timezone_str' => $timezone,
            'limit' => $limit,
            'offset' => $offset
        ];

        return $this->makeRequest("reports/user-time-sheet/{$this->organizationId}/{$userId}", 'GET', [], $params);
    }

    /**
     * Get user daily report (single day)
     *
     * @param string $userId User ID
     * @param string $date Date (YYYY-MM-DD)
     * @param string $timezone Timezone string
     * @return array|null Daily report or null on failure
     */
    public function getUserDailyReport($userId, $date, $timezone = 'Europe/Istanbul') {
        return $this->getUserTimeSheet($userId, $date, $date, $timezone, 1, 0);
    }

    /**
     * Get user monthly report
     *
     * @param string $userId User ID
     * @param string $year Year (YYYY)
     * @param string $month Month (MM)
     * @param string $timezone Timezone string
     * @return array|null Monthly report or null on failure
     */
    public function getUserMonthlyReport($userId, $year, $month, $timezone = 'Europe/Istanbul') {
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month

        return $this->getUserTimeSheet($userId, $startDate, $endDate, $timezone, 31, 0);
    }

    /**
     * Test API connection
     *
     * @return bool True if connection successful
     */
    public function testConnection() {
        $token = $this->getAccessToken();
        return $token !== null;
    }

    /**
     * Get organization ID
     *
     * @return int Organization ID
     */
    public function getOrganizationId() {
        return $this->organizationId;
    }

    /**
     * Set organization ID
     *
     * @param int $orgId Organization ID
     * @return void
     */
    public function setOrganizationId($orgId) {
        $this->organizationId = $orgId;
    }

    /**
     * Update user password
     *
     * @param string $userId User ID
     * @param string $newPassword New password
     * @return array|null Response or null on failure
     */
    public function updateUserPassword($userId, $newPassword) {
        $data = [
            'new_password' => $newPassword,
            'confirm_password' => $newPassword
        ];

        $queryParams = [
            'user_id' => $userId
        ];

        return $this->makeRequest("user/reset-password/{$this->organizationId}", 'PUT', $data, $queryParams);
    }

    /**
     * Log API activity to database
     *
     * @param string $action Action performed
     * @param string $endpoint API endpoint
     * @param bool $success Whether the action was successful
     * @param string $message Additional message
     * @return void
     */
    public function logActivity($action, $endpoint, $success = true, $message = '') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs
                (user_id, action, entity_type, entity_id, description, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $description = json_encode([
                'endpoint' => $endpoint,
                'success' => $success,
                'message' => $message,
                'organization_id' => $this->organizationId
            ]);

            $stmt->execute([
                $_SESSION['user_id'] ?? 0,
                $action,
                'timeworks_api',
                null,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("TimeWorksAPI: Failed to log activity - " . $e->getMessage());
        }
    }

    // =========================================================================
    // USER ENDPOINTS
    // =========================================================================

    /**
     * Create a new user
     * Adds a new user to the organization
     *
     * @param array $userData User data (email, full_name, role_id, etc.)
     * @return array|null Created user data or null
     */
    public function createUser($userData) {
        return $this->makeRequest("user/create/{$this->organizationId}", 'POST', $userData);
    }

    /**
     * Get single user details
     * Returns full details for the specified user
     *
     * @param string $userId User UUID
     * @return array|null User details or null
     */
    public function getUser($userId) {
        return $this->makeRequest("get-user/{$this->organizationId}/{$userId}", 'GET');
    }

    /**
     * Update user information
     * Updates user profile information
     *
     * @param array $userData Data to update (must include user_id)
     * @return array|null Updated user data or null
     */
    public function updateUser($userData) {
        return $this->makeRequest("user/update/{$this->organizationId}", 'PUT', $userData);
    }

    /**
     * Update user by manager
     * Updates user information with manager privileges (partial update)
     *
     * @param string $userId User UUID
     * @param array $userData Fields to update
     * @return array|null Updated user data or null
     */
    public function updateUserByManager($userId, $userData) {
        return $this->makeRequest("user/update-by-manager/{$this->organizationId}/{$userId}", 'PATCH', $userData);
    }

    /**
     * Delete user (soft delete)
     * Removes user from system via soft-delete (recoverable)
     *
     * @param string $userId User UUID
     * @return array|null Deletion result or null
     */
    public function deleteUser($userId) {
        return $this->makeRequest("user/{$this->organizationId}/{$userId}", 'DELETE');
    }

    /**
     * Get all users simple list
     * Returns all users with basic info (id, name) - ideal for dropdowns
     *
     * @param string|null $search Search term (optional)
     * @return array|null User list or null
     */
    public function getAllUsers($search = null) {
        $params = [];
        if ($search) {
            $params['search'] = $search;
        }
        return $this->makeRequest("all/user/{$this->organizationId}", 'GET', [], $params);
    }

    /**
     * Get current user details
     * Returns details of the token owner user
     *
     * @return array|null User details or null
     */
    public function getCurrentUserDetails() {
        return $this->makeRequest("user/details", 'GET');
    }

    /**
     * Change user password
     * For user to change their own password (requires old password)
     *
     * @param string $oldPassword Current password
     * @param string $newPassword New password
     * @return array|null Result or null
     */
    public function changePassword($oldPassword, $newPassword) {
        $data = [
            'old_password' => $oldPassword,
            'new_password' => $newPassword,
            'confirm_password' => $newPassword
        ];
        return $this->makeRequest("user/change-password/{$this->organizationId}", 'PUT', $data);
    }

    // =========================================================================
    // PROJECT ENDPOINTS
    // =========================================================================

    /**
     * Create a new project
     * Adds a new project to the organization
     *
     * @param array $projectData Project data (name, client_id, status, etc.)
     * @return array|null Created project data or null
     */
    public function createProject($projectData) {
        return $this->makeRequest("projects/{$this->organizationId}", 'POST', $projectData);
    }

    /**
     * Get single project details
     * Returns full details for the specified project
     *
     * @param string $projectId Project UUID
     * @return array|null Project details or null
     */
    public function getProject($projectId) {
        return $this->makeRequest("project/{$this->organizationId}/{$projectId}", 'GET');
    }

    /**
     * Update project information
     * Updates project information (partial update)
     *
     * @param string $projectId Project UUID
     * @param array $projectData Fields to update
     * @return array|null Updated project data or null
     */
    public function updateProject($projectId, $projectData) {
        return $this->makeRequest("projects/{$this->organizationId}/{$projectId}", 'PATCH', $projectData);
    }

    /**
     * Get all projects simple list
     * Returns all projects with basic info - ideal for dropdowns
     *
     * @param string|null $userId Filter by user (optional)
     * @return array|null Project list or null
     */
    public function getAllProjects($userId = null) {
        $params = [];
        if ($userId) {
            $params['user_id'] = $userId;
        }
        return $this->makeRequest("all/projects/{$this->organizationId}", 'GET', [], $params);
    }

    /**
     * Add project member
     * Assigns a user to the specified project
     *
     * @param string $projectId Project UUID
     * @param array $memberData Member data (user_id, role, etc.)
     * @return array|null Result or null
     */
    public function addProjectMember($projectId, $memberData) {
        return $this->makeRequest("project-members/{$this->organizationId}/{$projectId}", 'POST', $memberData);
    }

    /**
     * Remove project member
     * Removes a user from the specified project
     *
     * @param string $projectId Project UUID
     * @param string $userId User UUID
     * @return array|null Result or null
     */
    public function removeProjectMember($projectId, $userId) {
        return $this->makeRequest("project-members/{$this->organizationId}/{$projectId}/{$userId}", 'DELETE');
    }

    // =========================================================================
    // CLIENT ENDPOINTS
    // =========================================================================

    /**
     * Get clients list
     * Returns all clients in the organization with pagination
     *
     * @param int $limit Records per page
     * @param int $offset Starting point
     * @param string|null $search Search term
     * @param string|null $status Status filter (active/inactive)
     * @return array|null Client list or null
     */
    public function getClients($limit = 100, $offset = 0, $search = null, $status = null) {
        $params = [
            'limit' => $limit,
            'offset' => $offset
        ];
        if ($search) $params['search'] = $search;
        if ($status) $params['status'] = $status;

        return $this->makeRequest("client/list/{$this->organizationId}", 'GET', [], $params);
    }

    /**
     * Create a new client
     * Adds a new client to the organization
     *
     * @param array $clientData Client data (name, email, phone, etc.)
     * @return array|null Created client data or null
     */
    public function createClient($clientData) {
        return $this->makeRequest("client/create/{$this->organizationId}", 'POST', $clientData);
    }

    /**
     * Get single client details
     * Returns full details for the specified client
     *
     * @param int $clientId Client ID
     * @return array|null Client details or null
     */
    public function getClient($clientId) {
        return $this->makeRequest("client/{$this->organizationId}/{$clientId}", 'GET');
    }

    /**
     * Update client information
     * Updates client information
     *
     * @param int $clientId Client ID
     * @param array $clientData Data to update
     * @return array|null Updated client data or null
     */
    public function updateClient($clientId, $clientData) {
        $params = ['organization_id' => $this->organizationId];
        return $this->makeRequest("client/update/{$clientId}", 'PUT', $clientData, $params);
    }

    /**
     * Get all clients simple list
     * Returns all clients with basic info - ideal for dropdowns
     *
     * @param string|null $search Search term (optional)
     * @return array|null Client list or null
     */
    public function getAllClients($search = null) {
        $params = [];
        if ($search) {
            $params['search'] = $search;
        }
        return $this->makeRequest("all/clients/{$this->organizationId}", 'GET', [], $params);
    }

    // =========================================================================
    // FINANCE / RATES ENDPOINTS
    // =========================================================================

    /**
     * Add bill rate for user
     * Sets the hourly rate to be billed to client for this user
     *
     * @param string $userId User UUID
     * @param array $rateData Rate data (rate, currency, effective_date, etc.)
     * @return array|null Result or null
     */
    public function addBillRate($userId, $rateData) {
        return $this->makeRequest("bill-rate/user/{$this->organizationId}/{$userId}", 'PUT', $rateData);
    }

    /**
     * Add pay rate for user
     * Sets the hourly rate to be paid to this user
     *
     * @param string $userId User UUID
     * @param array $rateData Rate data (rate, currency, effective_date, etc.)
     * @return array|null Result or null
     */
    public function addPayRate($userId, $rateData) {
        return $this->makeRequest("pay-rate/user/{$this->organizationId}/{$userId}", 'PUT', $rateData);
    }

    /**
     * Edit user bill rate
     * Updates an existing bill rate
     *
     * @param string $userId User UUID
     * @param int $billId Bill rate ID
     * @param array $rateData Rate data to update
     * @return array|null Result or null
     */
    public function editBillRate($userId, $billId, $rateData) {
        return $this->makeRequest("edit-bill-rate/user/{$this->organizationId}/{$userId}/{$billId}", 'PUT', $rateData);
    }

    /**
     * Edit user pay rate
     * Updates an existing pay rate
     *
     * @param string $userId User UUID
     * @param int $payId Pay rate ID
     * @param array $rateData Rate data to update
     * @return array|null Result or null
     */
    public function editPayRate($userId, $payId, $rateData) {
        return $this->makeRequest("edit-pay-rate/user/{$this->organizationId}/{$userId}/{$payId}", 'PUT', $rateData);
    }

    /**
     * Get finance users list
     * Returns user list with rate information
     *
     * @param int $limit Records per page
     * @param int $offset Starting point
     * @param string|null $search Search term
     * @return array|null User list or null
     */
    public function getFinanceUsers($limit = 100, $offset = 0, $search = null) {
        $params = [
            'organization_id' => $this->organizationId,
            'limit' => $limit,
            'offset' => $offset
        ];
        if ($search) $params['search'] = $search;

        return $this->makeRequest("finance/user-list", 'GET', [], $params);
    }

    // =========================================================================
    // UTILITY ENDPOINTS
    // =========================================================================

    /**
     * Get supported timezones list
     * Returns all available timezones in the system
     *
     * @return array|null Timezone list or null
     */
    public function getTimezones() {
        return $this->makeRequest("timezones", 'GET');
    }

    /**
     * Get organization info
     * Returns details for the specified organization
     *
     * @param int|null $orgId Organization ID (uses current org if null)
     * @return array|null Organization info or null
     */
    public function getOrganization($orgId = null) {
        $id = $orgId ?? $this->organizationId;
        return $this->makeRequest("organizations/{$id}", 'GET');
    }

    /**
     * Get organizations list
     * Returns all organizations with pagination
     *
     * @param int $limit Records per page
     * @param int $offset Starting point
     * @return array|null Organization list or null
     */
    public function getOrganizations($limit = 100, $offset = 0) {
        $params = [
            'limit' => $limit,
            'offset' => $offset
        ];
        return $this->makeRequest("organizations", 'GET', [], $params);
    }

    // =========================================================================
    // TIME OFF / LEAVE ENDPOINTS
    // =========================================================================

    /**
     * Get time off requests from TimeWorks
     * Endpoint: /api/v1/time-off/requests/{org_id}
     *
     * @param int $limit Records per page
     * @param int $offset Pagination offset
     * @param string $timezone Timezone string
     * @return array|null Time off requests or null
     */
    public function getTimeOffRequests($limit = 100, $offset = 0, $timezone = 'America/New_York') {
        $params = [
            'limit' => $limit,
            'offset' => $offset,
            'timezone' => $timezone
        ];

        return $this->makeRequest("time-off/requests/{$this->organizationId}", 'GET', [], $params);
    }

    /**
     * Get all time off requests with pagination
     * Fetches all pages until no more results
     *
     * @param int $limit Records per page
     * @param string $timezone Timezone string
     * @return array All time off requests
     */
    public function getAllTimeOffRequests($limit = 100, $timezone = 'America/New_York') {
        $allRequests = [];
        $offset = 0;

        do {
            $response = $this->getTimeOffRequests($limit, $offset, $timezone);

            if (!$response) {
                if ($offset === 0) {
                    error_log("TimeWorksAPI: Failed to fetch time off requests at offset 0");
                }
                break;
            }

            // Handle different response formats
            $requests = [];
            if (isset($response['data'])) {
                $requests = $response['data'];
            } elseif (isset($response['items'])) {
                $requests = $response['items'];
            } elseif (isset($response['requests'])) {
                $requests = $response['requests'];
            } elseif (isset($response['time_off_requests'])) {
                $requests = $response['time_off_requests'];
            } elseif (is_array($response) && !isset($response['success']) && !isset($response['message'])) {
                // Response might be the array directly
                $requests = $response;
            }

            if (empty($requests)) {
                break;
            }

            $allRequests = array_merge($allRequests, $requests);
            $offset += $limit;

            // Get total from response if available
            $total = $response['total'] ?? 0;

            // Stop if we got fewer than requested (last page) or reached total
        } while (count($requests) >= $limit && ($total == 0 || $offset < $total));

        return $allRequests;
    }

    /**
     * Get time off types/categories
     *
     * @return array|null Time off types or null
     */
    public function getTimeOffTypes() {
        return $this->makeRequest("time-off/types/{$this->organizationId}", 'GET');
    }
}
