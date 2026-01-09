<?php
/**
 * Hubstaff API Helper Class
 *
 * Aralık 2025 timesheet export için geçici modül
 */

class HubstaffAPI {
    // Hard-coded Personal Access Token (Refresh Token)
    private const PAT_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6ImRlZmF1bHQifQ.eyJqdGkiOiJVRktzbE9rQSIsImlzcyI6Imh0dHBzOi8vYWNjb3VudC5odWJzdGFmZi5jb20iLCJleHAiOjE3NzQ5NDE2OTgsImlhdCI6MTc2NzE2NTY5OCwic2NvcGUiOiJvcGVuaWQgcHJvZmlsZSBlbWFpbCBodWJzdGFmZjpyZWFkIn0.JseAY5DdY1s3x2hjrxvEqIqQRl9Qnv5EFkxy9tNXIltjY3dGlU3mudpAmM81Tv7iK_v4_Tyvu2OmQ7Xc1vsqesV4VitQ4UF-v-UknpbebAa6ReA3Vv-6uQRAkKY2XpoVqLpccWZfeD2fL9qiDNsPB0Bxq5PT_vBGgiC98f3a51bSpZrtW0m0gCkFSZd--81rO8p_MWq2njij6RTeq9ksuzK66CETRKAJ6rI8tCV9BnQsOiHXBbAd7STgF1mFIV8LBeaKeTN4rMa9J54t7I_TmXsbhLWIAxUqXJTHcZ3nvaFVjqzQLX4ht60u0EXcQjOOW0vqRj34mn1qipgx9Hgk8A';

    // Date range for export
    private const START_DATE = '2025-12-01';
    private const END_DATE = '2025-12-31';

    // API endpoints
    private const TOKEN_URL = 'https://account.hubstaff.com/access_tokens';
    private const API_BASE_URL = 'https://api.hubstaff.com';

    private $accessToken = null;
    private const TOKEN_CACHE_FILE = __DIR__ . '/../exports/.token_cache.json';

    /**
     * Get access token using PAT (refresh token)
     * Caches token to avoid rate limits
     */
    public function getAccessToken() {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        // Try to load from cache first
        if (file_exists(self::TOKEN_CACHE_FILE)) {
            $cache = json_decode(file_get_contents(self::TOKEN_CACHE_FILE), true);
            if ($cache && isset($cache['access_token']) && isset($cache['expires_at'])) {
                // Token is valid if it expires in more than 5 minutes
                if ($cache['expires_at'] > time() + 300) {
                    $this->accessToken = $cache['access_token'];
                    return $this->accessToken;
                }
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => self::PAT_TOKEN
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Token alma hatası: HTTP $httpCode - $response");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("Access token alınamadı: $response");
        }

        $this->accessToken = $data['access_token'];

        // Cache the token (default 24 hours if no expires_in)
        $expiresIn = $data['expires_in'] ?? 86400;
        $cacheData = [
            'access_token' => $this->accessToken,
            'expires_at' => time() + $expiresIn
        ];
        @file_put_contents(self::TOKEN_CACHE_FILE, json_encode($cacheData));

        return $this->accessToken;
    }

    /**
     * Make API GET request
     */
    private function apiGet($endpoint, $params = []) {
        $token = $this->getAccessToken();

        $url = self::API_BASE_URL . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API hatası ($endpoint): HTTP $httpCode - $response");
        }

        return json_decode($response, true);
    }

    /**
     * Get current user
     */
    public function getCurrentUser() {
        return $this->apiGet('/v2/users/me');
    }

    /**
     * Get organizations
     */
    public function getOrganizations() {
        return $this->apiGet('/v2/organizations');
    }

    /**
     * Get organization members
     */
    public function getMembers($orgId) {
        $allMembers = [];
        $page = 1;

        do {
            $response = $this->apiGet("/v2/organizations/$orgId/members", [
                'page_start_id' => $page,
                'page_limit' => 100
            ]);

            if (isset($response['members'])) {
                $allMembers = array_merge($allMembers, $response['members']);
            }

            // Check for more pages
            $hasMore = isset($response['pagination']['next_page_start_id']);
            if ($hasMore) {
                $page = $response['pagination']['next_page_start_id'];
            }
        } while ($hasMore);

        return $allMembers;
    }

    /**
     * Get daily activities for a user
     */
    public function getDailyActivities($orgId, $userId) {
        $allActivities = [];
        $page = 1;

        do {
            $response = $this->apiGet("/v2/organizations/$orgId/activities/daily", [
                'date[start]' => self::START_DATE,
                'date[stop]' => self::END_DATE,
                'user_ids[]' => $userId,
                'page_start_id' => $page,
                'page_limit' => 100
            ]);

            if (isset($response['daily_activities'])) {
                $allActivities = array_merge($allActivities, $response['daily_activities']);
            }

            // Check for more pages
            $hasMore = isset($response['pagination']['next_page_start_id']);
            if ($hasMore) {
                $page = $response['pagination']['next_page_start_id'];
            }
        } while ($hasMore);

        return $allActivities;
    }

    /**
     * Get time entries for the organization (all users)
     * This returns detailed time entry data with start/stop times
     */
    public function getTimeEntries($orgId, $userId = null) {
        $allEntries = [];
        $page = 1;

        do {
            $params = [
                'date[start]' => self::START_DATE,
                'date[stop]' => self::END_DATE,
                'page_start_id' => $page,
                'page_limit' => 500
            ];

            if ($userId) {
                $params['user_ids[]'] = $userId;
            }

            $response = $this->apiGet("/v2/organizations/$orgId/time_entries", $params);

            if (isset($response['time_entries'])) {
                $allEntries = array_merge($allEntries, $response['time_entries']);
            }

            // Check for more pages
            $hasMore = isset($response['pagination']['next_page_start_id']);
            if ($hasMore) {
                $page = $response['pagination']['next_page_start_id'];
            }
        } while ($hasMore);

        return $allEntries;
    }

    /**
     * Get user details
     */
    public function getUser($userId) {
        return $this->apiGet("/v2/users/$userId");
    }

    /**
     * Get all users (with caching)
     */
    public function getUsers($orgId) {
        $allUsers = [];
        $members = $this->getMembers($orgId);

        foreach ($members as $member) {
            $userId = $member['user_id'];
            try {
                $userResponse = $this->getUser($userId);
                if (isset($userResponse['user'])) {
                    $allUsers[$userId] = $userResponse['user'];
                }
            } catch (Exception $e) {
                // Skip if user not found
                $allUsers[$userId] = ['id' => $userId, 'name' => 'User ' . $userId];
            }
        }

        return $allUsers;
    }

    /**
     * Get all projects for organization
     */
    public function getProjects($orgId) {
        $allProjects = [];
        $page = 1;

        do {
            $response = $this->apiGet("/v2/organizations/$orgId/projects", [
                'page_start_id' => $page,
                'page_limit' => 100
            ]);

            if (isset($response['projects'])) {
                foreach ($response['projects'] as $project) {
                    $allProjects[$project['id']] = $project;
                }
            }

            // Check for more pages
            $hasMore = isset($response['pagination']['next_page_start_id']);
            if ($hasMore) {
                $page = $response['pagination']['next_page_start_id'];
            }
        } while ($hasMore);

        return $allProjects;
    }

    /**
     * Get date range
     */
    public function getDateRange() {
        return [
            'start' => self::START_DATE,
            'end' => self::END_DATE
        ];
    }

    /**
     * Sanitize filename
     */
    public function sanitizeFilename($name) {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        return strtolower(trim($name, '_'));
    }
}
