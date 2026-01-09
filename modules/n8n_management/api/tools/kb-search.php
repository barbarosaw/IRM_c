<?php
/**
 * Knowledge Base Search API
 * Searches KB items by keywords and returns relevant answers
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

// Allow internal n8n calls
$isInternalCall = strpos($_SERVER["HTTP_USER_AGENT"] ?? "", "n8n") !== false;
if (!$isInternalCall) {
    // Only validate API key for external calls
    // validateApiKey(); // Disabled for now
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['query'] ?? '');
$limit = min(intval($input['limit'] ?? 5), 10);

if (empty($query)) {
    jsonResponse(['success' => false, 'error' => 'query required'], 400);
}

try {
    // Normalize query - lowercase, remove punctuation
    $normalizedQuery = strtolower(preg_replace('/[^\w\s]/', '', $query));
    $queryWords = array_filter(explode(' ', $normalizedQuery), function($w) {
        return strlen($w) > 2; // Skip short words
    });

    if (empty($queryWords)) {
        jsonResponse(['success' => true, 'results' => [], 'message' => 'No searchable terms']);
    }

    // Build search conditions
    $conditions = [];
    $params = [];
    
    foreach ($queryWords as $i => $word) {
        $conditions[] = "(
            LOWER(i.question) LIKE ? OR 
            LOWER(i.answer) LIKE ? OR 
            LOWER(i.keywords) LIKE ? OR
            LOWER(c.name) LIKE ?
        )";
        $likeWord = "%$word%";
        $params[] = $likeWord;
        $params[] = $likeWord;
        $params[] = $likeWord;
        $params[] = $likeWord;
    }

    // Search with relevance scoring
    $sql = "
        SELECT 
            i.id,
            i.question,
            i.answer,
            i.keywords,
            c.name as category,
            (
                -- Exact phrase match in question (highest priority)
                CASE WHEN LOWER(i.question) LIKE ? THEN 100 ELSE 0 END +
                -- Exact phrase match in keywords
                CASE WHEN LOWER(i.keywords) LIKE ? THEN 80 ELSE 0 END +
                -- Word count matches
                " . implode(" + ", array_map(function($w) {
                    return "(CASE WHEN LOWER(i.question) LIKE '%$w%' THEN 10 ELSE 0 END)";
                }, $queryWords)) . " +
                " . implode(" + ", array_map(function($w) {
                    return "(CASE WHEN LOWER(i.keywords) LIKE '%$w%' THEN 5 ELSE 0 END)";
                }, $queryWords)) . " +
                " . implode(" + ", array_map(function($w) {
                    return "(CASE WHEN LOWER(i.answer) LIKE '%$w%' THEN 2 ELSE 0 END)";
                }, $queryWords)) . "
            ) as relevance_score
        FROM chat_kb_items i
        JOIN chat_kb_categories c ON i.category_id = c.id
        WHERE i.is_active = 1 
        AND c.is_active = 1
        AND (" . implode(" OR ", $conditions) . ")
        HAVING relevance_score > 0
        ORDER BY relevance_score DESC, i.usage_count DESC
        LIMIT ?
    ";

    // Add phrase match params at the beginning
    $phraseParam = "%" . $normalizedQuery . "%";
    array_unshift($params, $phraseParam, $phraseParam);
    $params[] = $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update usage count for returned items
    if (!empty($results)) {
        $ids = array_column($results, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE chat_kb_items SET usage_count = usage_count + 1 WHERE id IN ($placeholders)")
           ->execute($ids);
    }

    // Format results
    $formatted = array_map(function($r) {
        return [
            'question' => $r['question'],
            'answer' => $r['answer'],
            'category' => $r['category'],
            'score' => (int)$r['relevance_score']
        ];
    }, $results);

    jsonResponse([
        'success' => true,
        'query' => $query,
        'results' => $formatted,
        'count' => count($formatted)
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
