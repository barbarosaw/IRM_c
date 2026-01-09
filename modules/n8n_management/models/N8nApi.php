<?php
/**
 * N8nApi Model
 * Wrapper for n8n API communication
 */

class N8nApi
{
    private $db;
    private $host;
    private $apiKey;

    public function __construct($db)
    {
        $this->db = $db;
        $this->loadSettings();
    }

    /**
     * Load n8n settings from database
     */
    private function loadSettings()
    {
        $stmt = $this->db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('n8n_host', 'n8n_api_key')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->host = rtrim($settings['n8n_host'] ?? '', '/');
        $this->apiKey = $settings['n8n_api_key'] ?? '';
    }

    /**
     * Check if n8n is configured
     */
    public function isConfigured()
    {
        return !empty($this->host) && !empty($this->apiKey);
    }

    /**
     * Make API request to n8n
     */
    private function request($endpoint, $method = 'GET', $data = null)
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'n8n is not configured'];
        }

        $url = $this->host . '/api/v1/' . ltrim($endpoint, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'X-N8N-API-KEY: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded, 'http_code' => $httpCode];
        }

        return [
            'success' => false,
            'error' => $decoded['message'] ?? 'API request failed',
            'http_code' => $httpCode
        ];
    }

    /**
     * Test connection to n8n
     */
    public function testConnection()
    {
        $result = $this->request('workflows');

        if ($result['success']) {
            $workflows = $result['data']['data'] ?? $result['data'] ?? [];
            return [
                'success' => true,
                'message' => 'Connection successful',
                'workflows' => count($workflows)
            ];
        }

        return $result;
    }

    /**
     * Get all workflows
     */
    public function getWorkflows($active = null)
    {
        $endpoint = 'workflows';
        if ($active !== null) {
            $endpoint .= '?active=' . ($active ? 'true' : 'false');
        }

        $result = $this->request($endpoint);

        if ($result['success']) {
            return [
                'success' => true,
                'workflows' => $result['data']['data'] ?? $result['data'] ?? []
            ];
        }

        return $result;
    }

    /**
     * Get workflow by ID
     */
    public function getWorkflow($id)
    {
        return $this->request('workflows/' . $id);
    }

    /**
     * Get workflow executions
     */
    public function getExecutions($workflowId = null, $limit = 100)
    {
        $endpoint = 'executions?limit=' . $limit;
        if ($workflowId) {
            $endpoint .= '&workflowId=' . $workflowId;
        }

        $result = $this->request($endpoint);

        if ($result['success']) {
            return [
                'success' => true,
                'executions' => $result['data']['data'] ?? $result['data'] ?? []
            ];
        }

        return $result;
    }

    /**
     * Get execution statistics
     */
    public function getExecutionStats($limit = 100)
    {
        $result = $this->getExecutions(null, $limit);

        if (!$result['success']) {
            return $result;
        }

        $executions = $result['executions'];
        $stats = [
            'total' => count($executions),
            'success' => 0,
            'error' => 0,
            'running' => 0
        ];

        foreach ($executions as $exec) {
            $status = $exec['status'] ?? 'unknown';
            if ($status === 'success') {
                $stats['success']++;
            } elseif ($status === 'error' || $status === 'failed') {
                $stats['error']++;
            } elseif ($status === 'running' || $status === 'waiting') {
                $stats['running']++;
            }
        }

        return ['success' => true, 'stats' => $stats];
    }

    /**
     * Get n8n host URL
     */
    public function getHost()
    {
        return $this->host;
    }
}
