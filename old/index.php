<?php
// MySQL configuration
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'irm_sys',
    'user' => 'irm_sys_sr',
    'password' => 'JEegMl1pf!@5l3ev'
];

// AJAX requests handler for user details
if (isset($_GET['ajax']) && isset($_GET['user_id'])) {
    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $userId = $_GET['user_id'];
        $stmt = $pdo->prepare("
            SELECT id, user_agent, timestamp, ip, request_method, referer, session_id, url 
            FROM fingerprints 
            WHERE user_id = :user_id 
            ORDER BY timestamp DESC 
            LIMIT 50
        ");
        $stmt->execute([':user_id' => $userId]);
        $details = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode($details);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Establish PDO connection for main page
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$sortBy = $_GET['sort'] ?? 'record_count';
$sortOrder = $_GET['order'] ?? 'DESC';
$search = $_GET['search'] ?? '';

// Valid sort columns
$validSorts = ['user_id', 'record_count', 'first_seen', 'last_seen', 'unique_ips', 'unique_sessions'];
if (!in_array($sortBy, $validSorts)) {
    $sortBy = 'record_count';
}
if (!in_array($sortOrder, ['ASC', 'DESC'])) {
    $sortOrder = 'DESC';
}

try {
    // Get top 10 most active users for chart
    $chartStmt = $pdo->query("
        SELECT user_id, COUNT(*) as count 
        FROM fingerprints 
        WHERE user_id IS NOT NULL AND user_id != '' 
        GROUP BY user_id 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $chartData = $chartStmt->fetchAll();

    // Get grouped data with statistics
    $searchCondition = '';
    $params = [];
    if ($search) {
        $searchCondition = "WHERE user_id LIKE :search";
        $params[':search'] = "%$search%";
    }

    $sql = "
        SELECT 
            user_id,
            COUNT(*) as record_count,
            MIN(timestamp) as first_seen,
            MAX(timestamp) as last_seen,
            COUNT(DISTINCT ip) as unique_ips,
            COUNT(DISTINCT session_id) as unique_sessions,
            COUNT(DISTINCT DATE(timestamp)) as active_days
        FROM fingerprints 
        WHERE user_id IS NOT NULL AND user_id != ''
        " . ($search ? "AND user_id LIKE :search" : "") . "
        GROUP BY user_id
        ORDER BY $sortBy $sortOrder
        LIMIT $perPage OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $groupedData = $stmt->fetchAll();

    // Get total count for pagination
    $countSql = "
        SELECT COUNT(DISTINCT user_id) as total
        FROM fingerprints 
        WHERE user_id IS NOT NULL AND user_id != ''
        " . ($search ? "AND user_id LIKE :search" : "");
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalUsers = $countStmt->fetch()['total'];
    $totalPages = ceil($totalUsers / $perPage);

    // Get overall statistics
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT user_id) as total_users,
            COUNT(DISTINCT ip) as total_ips,
            MIN(timestamp) as oldest_record,
            MAX(timestamp) as newest_record
        FROM fingerprints
        WHERE user_id IS NOT NULL AND user_id != ''
    ");
    $stats = $statsStmt->fetch();

} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Fingerprint Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f5f5f5;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .controls {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box, .sort-select {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-box:focus, .sort-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn:hover { background: #5a6fd8; }
        
        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table { 
            border-collapse: collapse; 
            width: 100%; 
        }
        
        th, td { 
            padding: 15px; 
            text-align: left; 
            border-bottom: 1px solid #eee;
        }
        
        th { 
            background: #f8f9fa; 
            font-weight: 600;
            cursor: pointer;
            position: relative;
        }
        
        th:hover { background: #e9ecef; }
        
        .sort-icon {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.5;
        }
        
        tr:hover { background-color: #f8f9fa; }
        
        .user-row {
            cursor: pointer;
        }
        
        .details-row {
            display: none;
            background: #f8f9fa;
        }
        
        .details-table {
            margin: 10px;
            font-size: 0.9em;
        }
        
        .details-table th {
            background: #e9ecef;
            padding: 8px;
        }
        
        .details-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .page-btn {
            padding: 8px 12px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }
        
        .page-btn:hover, .page-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .form-container { 
            max-width: 400px; 
            margin: 50px auto; 
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .form-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        
        input[type="password"] { 
            width: 100%; 
            padding: 12px; 
            margin: 10px 0; 
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        input[type="submit"] { 
            width: 100%;
            padding: 12px; 
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        input[type="submit"]:hover {
            background: #5a6fd8;
        }
        
        .error { 
            color: #dc3545; 
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8d7da;
            border-radius: 5px;
        }
        
        .badge {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box, .sort-select, .btn {
                width: 100%;
            }
            
            table {
                font-size: 0.9em;
            }
            
            th, td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Fingerprint Analytics Dashboard</h1>
            <p>Analyze and track user activities</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_records']); ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_ips']); ?></div>
                <div class="stat-label">Unique IPs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo round($stats['total_records'] / max(1, $stats['total_users']), 1); ?></div>
                <div class="stat-label">Avg Records/User</div>
            </div>
        </div>


            <!-- Controls -->
            <div class="controls">
                <input type="text" class="search-box" placeholder="Search user ID..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       onkeypress="if(event.key==='Enter') searchUsers()">
                
                <select class="sort-select" onchange="sortTable(this.value)">
                    <option value="record_count_DESC" <?php echo ($sortBy == 'record_count' && $sortOrder == 'DESC') ? 'selected' : ''; ?>>Most Records</option>
                    <option value="record_count_ASC" <?php echo ($sortBy == 'record_count' && $sortOrder == 'ASC') ? 'selected' : ''; ?>>Least Records</option>
                    <option value="last_seen_DESC" <?php echo ($sortBy == 'last_seen' && $sortOrder == 'DESC') ? 'selected' : ''; ?>>Last Seen (Recent)</option>
                    <option value="last_seen_ASC" <?php echo ($sortBy == 'last_seen' && $sortOrder == 'ASC') ? 'selected' : ''; ?>>Last Seen (Oldest)</option>
                    <option value="user_id_ASC" <?php echo ($sortBy == 'user_id' && $sortOrder == 'ASC') ? 'selected' : ''; ?>>User ID (A-Z)</option>
                    <option value="user_id_DESC" <?php echo ($sortBy == 'user_id' && $sortOrder == 'DESC') ? 'selected' : ''; ?>>User ID (Z-A)</option>
                    <option value="unique_ips_DESC" <?php echo ($sortBy == 'unique_ips' && $sortOrder == 'DESC') ? 'selected' : ''; ?>>Most IPs</option>
                </select>
                
                <button class="btn" onclick="searchUsers()">🔍 Search</button>
                <button class="btn" onclick="clearSearch()">🗑️ Clear</button>
            </div>

            <!-- User Table -->
            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th onclick="sort('user_id')">👤 User ID <span class="sort-icon">↕️</span></th>
                            <th onclick="sort('record_count')">📊 Record Count <span class="sort-icon">↕️</span></th>
                            <th onclick="sort('first_seen')">🕐 First Seen <span class="sort-icon">↕️</span></th>
                            <th onclick="sort('last_seen')">🕑 Last Seen <span class="sort-icon">↕️</span></th>
                            <th onclick="sort('unique_ips')">🌐 Unique IPs <span class="sort-icon">↕️</span></th>
                            <th onclick="sort('unique_sessions')">🔗 Unique Sessions <span class="sort-icon">↕️</span></th>
                            <th>📅 Active Days</th>
                            <th>⚡ Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupedData as $index => $user): ?>
                            <tr class="user-row" onclick="toggleDetails(<?php echo $index; ?>)">
                                <td><strong><?php echo htmlspecialchars($user['user_id']); ?></strong></td>
                                <td>
                                    <span class="badge"><?php echo number_format($user['record_count']); ?></span>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['first_seen'])); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($user['last_seen'])); ?></td>
                                <td><?php echo $user['unique_ips']; ?></td>
                                <td><?php echo $user['unique_sessions']; ?></td>
                                <td><?php echo $user['active_days']; ?></td>
                                <td><button class="btn" style="padding: 5px 10px; font-size: 12px;">👁️ Details</button></td>
                            </tr>
                            <tr class="details-row" id="details-<?php echo $index; ?>">
                                <td colspan="8">
                                    <div id="details-content-<?php echo $index; ?>">
                                        <em>Click to load details...</em>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">⏮️ First</a>
                        <a href="?page=<?php echo $page-1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">⬅️ Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?page=<?php echo $i; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-btn <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">Next ➡️</a>
                        <a href="?page=<?php echo $totalPages; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">Last ⏭️</a>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; color: #666; margin-top: 10px;">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total <?php echo number_format($totalUsers); ?> users)
                </div>
            <?php endif; ?>
		<p>&nbsp;</p>
		
            <!-- Top Active Users Chart -->
            <div class="chart-container">
                <h3>📈 Top 10 Most Active Users</h3>
                <canvas id="activityChart" width="400" height="200"></canvas>
            </div>
		
        </div>

        <script>
        // Grafik oluştur
        const ctx = document.getElementById('activityChart').getContext('2d');
        const chartData = <?php echo json_encode($chartData); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.map(item => item.user_id),
                datasets: [{
                    label: 'Record Count',
                    data: chartData.map(item => item.count),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Detay göster/gizle
        function toggleDetails(index) {
            const detailsRow = document.getElementById('details-' + index);
            const detailsContent = document.getElementById('details-content-' + index);
            
            if (detailsRow.style.display === 'table-row') {
                detailsRow.style.display = 'none';
            } else {
                detailsRow.style.display = 'table-row';
                
                // Load details if needed
                if (detailsContent.innerHTML.includes('Click to load')) {
                    const userId = detailsRow.previousElementSibling.cells[0].textContent.trim();
                    loadUserDetails(userId, index);
                }
            }
        }

        // Load user details
        function loadUserDetails(userId, index) {
            const detailsContent = document.getElementById('details-content-' + index);
            detailsContent.innerHTML = '<em>Loading...</em>';
            
            fetch('?ajax=1&user_id=' + encodeURIComponent(userId))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    if (data.length === 0) {
                        detailsContent.innerHTML = '<em>No records found for this user</em>';
                        return;
                    }
                    
                    let html = '<table class="details-table"><thead><tr>';
                    html += '<th>ID</th><th>User Agent</th><th>Time</th><th>IP</th><th>Method</th><th>Referer</th><th>Session ID</th><!--th>URL</th-->';
                    html += '</tr></thead><tbody>';
                    
                    data.forEach(row => {
                        html += '<tr>';
                        html += '<td>' + (row.id || 'N/A') + '</td>';
                        html += '<td>' + (row.user_agent || 'N/A') + '</td>';
                        html += '<td>' + (row.timestamp || 'N/A') + '</td>';
                        html += '<td>' + (row.ip || 'N/A') + '</td>';
                        html += '<td>' + (row.request_method || 'N/A') + '</td>';
                        html += '<td>' + (row.referer || 'N/A') + '</td>';
                        html += '<td>' + (row.session_id || 'N/A') + '</td>';
                        /* html += '<td>' + (row.url || 'N/A') + '</td>'; */
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    detailsContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    detailsContent.innerHTML = '<em>Error: Could not load details - ' + error.message + '</em>';
                });
        }

        // Sıralama
        function sort(column) {
            const currentSort = '<?php echo $sortBy; ?>';
            const currentOrder = '<?php echo $sortOrder; ?>';
            let newOrder = 'DESC';
            
            if (currentSort === column && currentOrder === 'DESC') {
                newOrder = 'ASC';
            }
            
            window.location.href = updateUrlParameter(window.location.href, 'sort', column);
            window.location.href = updateUrlParameter(window.location.href, 'order', newOrder);
        }

        // Tablo sıralama
        function sortTable(value) {
            const [sort, order] = value.split('_');
            let url = updateUrlParameter(window.location.href, 'sort', sort);
            url = updateUrlParameter(url, 'order', order);
            url = updateUrlParameter(url, 'page', '1');
            window.location.href = url;
        }

        // Arama
        function searchUsers() {
            const searchValue = document.querySelector('.search-box').value;
            let url = updateUrlParameter(window.location.href, 'search', searchValue);
            url = updateUrlParameter(url, 'page', '1');
            window.location.href = url;
        }

        // Arama temizle
        function clearSearch() {
            let url = removeUrlParameter(window.location.href, 'search');
            url = updateUrlParameter(url, 'page', '1');
            window.location.href = url;
        }

        // URL parameter güncelle
        function updateUrlParameter(url, param, paramVal) {
            let newAdditionalURL = "";
            let tempArray = url.split("?");
            let baseURL = tempArray[0];
            let additionalURL = tempArray[1];
            let temp = "";
            if (additionalURL) {
                tempArray = additionalURL.split("&");
                for (let i = 0; i < tempArray.length; i++) {
                    if (tempArray[i].split('=')[0] != param) {
                        newAdditionalURL += temp + tempArray[i];
                        temp = "&";
                    }
                }
            }
            let rows_txt = temp + "" + param + "=" + paramVal;
            return baseURL + "?" + newAdditionalURL + rows_txt;
        }

        // URL parameter kaldır
        function removeUrlParameter(url, parameter) {
            return url
                .replace(new RegExp('[?&]' + parameter + '=[^&#]*(#.*)?$'), '$1')
                .replace(new RegExp('([?&])' + parameter + '=[^&]*&'), '$1');
        }
        </script>

   
</body>
</html>