<?php
/**
 * AbroadWorks Management System - Fingerprint Module Dashboard
 * 
 * @author ikinciadam@gmail.com
 */

// Define system constant to prevent direct access to module files
define('AW_SYSTEM', true);

// Include required files
require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check - use the same permission as menu item
if (!has_permission('view_fingerprint')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = 'fingerprint'");
$stmt->execute();
$is_active = $stmt->fetchColumn();

// If module is not active and user is not an owner, redirect to module-inactive page
if ($is_active === false || $is_active == 0) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        header('Location: ../../module-inactive.php?module=fingerprint');
        exit;
    }
}

// Include model
require_once 'models/Fingerprint.php';

// Initialize model
$fingerprintModel = new Fingerprint();

// Get filter parameters
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'ip' => $_GET['ip'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;

// Get sorting parameters
$orderBy = $_GET['order_by'] ?? 'last_activity';
$orderDir = $_GET['order_dir'] ?? 'DESC';

// Get data for dashboard
$stats = $fingerprintModel->getDashboardStats();
$topBrowsers = $fingerprintModel->getTopBrowsers(5);
$dailyActivity = $fingerprintModel->getDailyActivity();
$topCountries = $fingerprintModel->getTopCountries(5);
$topUsers = $fingerprintModel->getTopUsers(10);
$suspiciousIPs = $fingerprintModel->getSuspiciousIPs(20);
$geographicData = $fingerprintModel->getGeographicData();
$unusualLogins = $fingerprintModel->getUnusualLoginTimes();
$rapidChanges = $fingerprintModel->getRapidLocationChanges();
$multipleBrowsers = $fingerprintModel->getMultipleBrowserUsage();
$vpnDetection = $fingerprintModel->getVPNProxyDetection();
$concurrentSessions = $fingerprintModel->getConcurrentSessions();
$recentFingerprints = $fingerprintModel->getRecentFingerprints($page, $limit, $orderBy, $orderDir, $filters);
$totalUsersCount = $fingerprintModel->getTotalUsersCount($filters);

// If AJAX request for user details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'user_details' && isset($_GET['user_id'])) {
    $userFingerprints = $fingerprintModel->getUserFingerprints($_GET['user_id']);
    header('Content-Type: application/json');
    echo json_encode($userFingerprints);
    exit;
}

// If AJAX request for IP details
if (isset($_GET['action']) && $_GET['action'] === 'get_ip_details' && isset($_GET['ip'])) {
    $ipDetails = $fingerprintModel->getIPDetails($_GET['ip']);
    header('Content-Type: application/json');
    echo json_encode($ipDetails);
    exit;
}

// If AJAX request for table data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'table') {
    $fingerprints = $fingerprintModel->getAllFingerprints($page, $limit, $filters);
    $totalCount = $fingerprintModel->getTotalCount($filters);
    
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $fingerprints,
        'total' => $totalCount,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => ceil($totalCount / $limit)
    ]);
    exit;
}

// Set page title
$page_title = 'Fingerprint Analytics';
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Include header and components
include '../../components/header.php';
include '../../components/sidebar.php';

// Include dashboard view
include 'views/dashboard.php';

// Include footer
include '../../components/footer.php';
?>
