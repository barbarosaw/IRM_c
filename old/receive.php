<?php
// MySQL configuration
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'irm_sys',
    'user' => 'irm_sys_sr',
    'password' => 'JEegMl1pf!@5l3ev'
];

// Validate API key
if (!isset($_SERVER['HTTP_X_API_KEY']) || $_SERVER['HTTP_X_API_KEY'] !== 'ed1e7ddd8885d718244278e1a3f9c7b6eec7b1a31cb6feef3a6fcd640f63559d') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Establish PDO connection
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()]);
    exit;
}

// Read POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data && isset($data['user_id'], $data['user_agent'], $data['timestamp'], $data['ip'])) {
    try {
        // Insert into fingerprints table
        $stmt = $pdo->prepare("INSERT INTO fingerprints (user_id, user_agent, timestamp, ip, request_method, referer, session_id, url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['user_id'],
            $data['user_agent'],
            $data['timestamp'],
            $data['ip'],
            $data['request_method'] ?? null,
            $data['referer'] ?? null,
            $data['session_id'] ?? null,
            $data['url'] ?? null
        ]);
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
}
?>