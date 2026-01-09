<?php
/**
 * AbroadWorks Management System - Helper Functions
 * 
 * @author ikinciadam@gmail.com
 */

/**
 * Apply system debug settings from database to PHP environment
 */
function apply_debug_settings() {
    // Apply display errors setting
    if (get_setting('display_errors', '1') == '1') {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        
        // Set error reporting level
        $error_level = get_setting('error_reporting', 'E_ALL');
        switch($error_level) {
            case 'E_ALL':
                error_reporting(E_ALL);
                break;
            case 'E_ALL & ~E_NOTICE':
                error_reporting(E_ALL & ~E_NOTICE);
                break;
            case 'E_ERROR | E_WARNING | E_PARSE':
                error_reporting(E_ERROR | E_WARNING | E_PARSE);
                break;
            default:
                error_reporting(E_ALL);
        }
    } else {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
    }
    
    // Apply other PHP settings
    ini_set('max_execution_time', get_setting('max_execution_time', '60'));
    ini_set('memory_limit', get_setting('memory_limit', '128M'));
    ini_set('post_max_size', get_setting('post_max_size', '12M'));
    ini_set('upload_max_filesize', get_setting('upload_max_filesize', '10M'));
}

/**
 * Clean user input to prevent XSS
 *
 * @param string $input User input to clean
 * @return string Sanitized input
 */
function clean_input($input) {
    if (is_array($input)) {
        return array_map('clean_input', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Password hashing with improved security
 *
 * @param string $password Plain text password
 * @return string Hashed password
 */
function password_hash_safe($password) {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * Get system setting from database
 *
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function get_setting($key, $default = null) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Get all settings from database
 *
 * @param string|null $group Optional group filter
 * @return array All settings
 */
function get_all_settings($group = null) {
    global $db;
    
    try {
        $query = "SELECT * FROM settings";
        
        if ($group) {
            $query .= " WHERE `group` = :group";
            $stmt = $db->prepare($query);
            $stmt->execute(['group' => $group]);
        } else {
            $stmt = $db->query($query);
        }
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        log_error('Error fetching settings: ' . $e->getMessage());
        return [];
    }
}

/**
 * Format date according to system settings
 *
 * @param string $date Date string to format
 * @param bool $with_time Whether to include time
 * @return string Formatted date
 */
function format_date($date, $with_time = false) {
    if (empty($date)) {
        return '';
    }
    
    $date_obj = new DateTime($date);
    
    $date_format = get_setting('date_format', 'Y-m-d');
    
    if ($with_time) {
        $time_format = get_setting('time_format', 'H:i');
        return $date_obj->format($date_format . ' ' . $time_format);
    }
    
    return $date_obj->format($date_format);
}

/**
 * Show message to user
 *
 * @param string $message Message to show
 * @param string $type Message type (success, danger, warning, info)
 * @return void
 */
function show_message($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

/**
 * Check if there is a message to show
 *
 * @return boolean
 */
function has_message() {
    return isset($_SESSION['message']) && !empty($_SESSION['message']);
}

/**
 * Get message to show
 *
 * @return string|null
 */
function get_message() {
    $message = $_SESSION['message'] ?? null;
    unset($_SESSION['message']);
    return $message;
}

/**
 * Get message type
 *
 * @return string
 */
function get_message_type() {
    $type = $_SESSION['message_type'] ?? 'success';
    unset($_SESSION['message_type']);
    return $type;
}

/**
 * Generate a random string
 * 
 * @param int $length Length of the string
 * @return string Random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $characters_length = strlen($characters);
    $random_string = '';
    
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, $characters_length - 1)];
    }
    
    return $random_string;
}

/**
 * Log system errors to file
 * 
 * @param string $message Error message
 * @param string $level Error level
 * @return void
 */
function log_error($message, $level = 'ERROR') {
    $log_dir = 'logs/';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . 'error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Check if user has access to a specific module
 * 
 * @param string $module Module code
 * @param bool $redirect Whether to redirect to module-inactive.php if module is inactive
 * @return boolean
 */
function has_module_access($module, $redirect = true) {
    global $db;
    
    // Owner users have all permissions (godmode)
    if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
        return true;
    }
    
    // Admin users have all permissions
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        return true;
    }
    
    // Check if module is active
    try {
        $stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
        $stmt->execute([$module]);
        $is_active = (int)$stmt->fetchColumn();
        
        // If module is not active, deny access for non-owner/non-admin users
        if ($is_active !== 1) {
            // Redirect to module-inactive page if requested
            if ($redirect) {
                header("Location: " . get_base_url() . "module-inactive.php?module=" . urlencode($module));
                exit;
            }
            return false;
        }
    } catch (Exception $e) {
        // If there's an error (e.g., modules table doesn't exist), continue with permission check
        log_error("Error checking module status: " . $e->getMessage());
    }
    
    // Check if module-level permission exists
    return has_permission($module . '-access');
}

/**
 * Get base URL for the application
 * 
 * @return string Base URL
 */
function get_base_url() {
    // Check if we're in a module directory
    $script_name = $_SERVER['SCRIPT_NAME'];
    if (strpos($script_name, '/modules/') !== false) {
        // We're in a module, so we need to go up two levels
        return '../../';
    }
    
    // We're in the root directory
    return '';
}

/**
 * Check if a module should be visible on dashboard for the current user
 * 
 * @param string $module Module code
 * @return boolean
 */
function is_module_visible($module) {
    global $db;
    
    // Owner users see all modules
    if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
        return true;
    }
    
    // Check if user has module access first
    if (!has_module_access($module)) {
        return false;
    }
    
    // Get user roles
    $user_roles = $_SESSION['user_roles'] ?? [];
    if (empty($user_roles)) {
        return false;
    }
    
    // Check if any of the user's roles has this module visible
    try {
        $placeholders = implode(',', array_fill(0, count($user_roles), '?'));
        $query = "
            SELECT mv.is_visible 
            FROM module_visibility mv
            JOIN roles r ON mv.role_id = r.id
            WHERE r.name IN ($placeholders) AND mv.module_code = ?
        ";
        
        $params = $user_roles;
        $params[] = $module;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If any role has it visible, show it
        return in_array(1, $results);
    } catch (Exception $e) {
        log_error('Error checking module visibility: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all available modules
 * 
 * @param boolean $active_only Only return active modules
 * @return array
 */
function get_all_modules($active_only = true) {
    global $db;
    
    try {
        $query = "SELECT * FROM modules";
        if ($active_only) {
            $query .= " WHERE is_active = 1";
        }
        $query .= " ORDER BY `order`";
        
        $stmt = $db->query($query);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        log_error('Error fetching modules: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get all permissions grouped by module
 * 
 * @return array
 */
function get_permissions_by_module() {
    global $db;
    
    $modules = [];
    
    try {
        // First get all modules
        $module_query = "SELECT * FROM modules ORDER BY `order`";
        $module_stmt = $db->query($module_query);
        $module_list = $module_stmt->fetchAll();
        
        // Initialize modules array
        foreach ($module_list as $module) {
            $modules[$module['code']] = [
                'name' => $module['name'],
                'description' => $module['description'],
                'icon' => $module['icon'],
                'permissions' => []
            ];
        }
        
        // Now get all permissions
        $permission_query = "SELECT * FROM permissions ORDER BY module, module_order, name";
        $permission_stmt = $db->query($permission_query);
        $permissions = $permission_stmt->fetchAll();
        
        // Group permissions by module
        foreach ($permissions as $permission) {
            $module = $permission['module'] ?? 'other';
            if (!isset($modules[$module])) {
                $modules[$module] = [
                    'name' => ucfirst($module),
                    'description' => 'Module permissions',
                    'icon' => 'fa-puzzle-piece',
                    'permissions' => []
                ];
            }
            
            $modules[$module]['permissions'][] = $permission;
        }
        
        return $modules;
    } catch (Exception $e) {
        log_error('Error fetching permissions by module: ' . $e->getMessage());
        return [];
    }
}

/**
 * Apply module permission check to sidebar links
 * Updates the sidebar to only show modules the user has permission to access
 */
function filter_sidebar_by_modules() {
    // This function could be extended to dynamically filter the sidebar menu
    // based on user's module access permissions
}

/**
 * Ensure timezone is properly set for the current request
 * Should be called early in the request lifecycle
 */
function initialize_timezone() {
    // Get timezone from settings or use default
    $timezone = get_setting('timezone', 'Europe/Istanbul');
    
    // Set the default timezone
    date_default_timezone_set($timezone);
    
    return $timezone;
}

/**
 * Get priority color class
 *
 * @param string $priority Priority level
 * @return string CSS class for the priority
 */
function get_priority_class($priority) {
    switch ($priority) {
        case 'high':
            return 'danger';
        case 'medium':
            return 'warning';
        case 'low':
            return 'success';
        default:
            return 'secondary';
    }
}

/**
 * Get status badge class
 *
 * @param string $status Vendor status
 * @return string CSS class for the status
 */
function get_status_badge_class($status) {
    switch ($status) {
        case 'active':
            return 'success';
        case 'inactive':
            return 'secondary';
        case 'potential':
            return 'info';
        case 'blacklisted':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Check if user can access vendor
 *
 * @param int $vendor_id Vendor ID
 * @param int $user_id User ID (optional, defaults to current user)
 * @return bool Whether user has access to this vendor
 */
function can_access_vendor($vendor_id, $user_id = null) {
    global $db;
    
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    // Admin and users with full vendor access can access all vendors
    if (is_admin() || has_permission('vendors-manage')) {
        return true;
    }
    
    // Check if user is assigned to this vendor
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM user_vendors 
        WHERE user_id = ? AND vendor_id = ?
    ");
    $stmt->execute([$user_id, $vendor_id]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Get document categories
 *
 * @return array List of document categories
 */
function get_document_categories() {
    global $db;
    
    try {
        $stmt = $db->query("SELECT * FROM document_categories ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [
            ['id' => 1, 'name' => 'Contract', 'description' => 'Vendor contracts and agreements'],
            ['id' => 2, 'name' => 'Price List', 'description' => 'Product and service price lists'],
            ['id' => 3, 'name' => 'Catalog', 'description' => 'Product catalogs'],
            ['id' => 4, 'name' => 'Certificate', 'description' => 'Quality certificates and compliance documents'],
            ['id' => 5, 'name' => 'Invoice', 'description' => 'Invoices from vendor'],
            ['id' => 6, 'name' => 'Other', 'description' => 'Other documents']
        ];
    }
}

/**
 * Log vendor communication activity
 *
 * @param int $vendor_id Vendor ID
 * @param string $type Communication type (email, phone, meeting, other)
 * @param string $subject Subject of the communication
 * @param string $content Content of the communication
 * @param string $contact_person Contact person name
 * @param string $followup_date Follow-up date (Y-m-d)
 * @param string $status Status of communication (pending, completed, no-action)
 * @return bool Success status
 */
function log_vendor_communication($vendor_id, $type, $subject, $content, $contact_person = null, $followup_date = null, $status = 'no-action') {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO vendor_communications (
                vendor_id, user_id, type, subject, content, contact_person, 
                followup_date, status, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            $vendor_id, $_SESSION['user_id'], $type, $subject, $content, 
            $contact_person, $followup_date, $status
        ]);
        
        // Log the activity
        log_activity($_SESSION['user_id'], 'create', 'vendor_communication', "Added communication record for vendor ID: $vendor_id");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error logging vendor communication: " . $e->getMessage());
        return false;
    }
}

/**
 * Get vendor communications with pagination
 * 
 * @param int $vendor_id Vendor ID
 * @param int $page Current page number
 * @param int $limit Records per page
 * @param string $type Optional filter by communication type
 * @param string $status Optional filter by status
 * @return array Array with communications and pagination info
 */
function get_vendor_communications($vendor_id, $page = 1, $limit = 10, $type = null, $status = null) {
    global $db;
    
    $offset = ($page - 1) * $limit;
    
    $query = "
        SELECT c.*, u.name as user_name 
        FROM vendor_communications c
        JOIN users u ON c.user_id = u.id
        WHERE c.vendor_id = ?
    ";
    
    $params = [$vendor_id];
    
    if ($type) {
        $query .= " AND c.type = ?";
        $params[] = $type;
    }
    
    if ($status) {
        $query .= " AND c.status = ?";
        $params[] = $status;
    }
    
    // Count query for pagination
    $count_query = str_replace("c.*, u.name as user_name", "COUNT(*) as total", $query);
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get paginated results
    $query .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $communications = $stmt->fetchAll();
    
    return [
        'communications' => $communications,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ];
}

/**
 * Get vendor documents with pagination
 * 
 * @param int $vendor_id Vendor ID
 * @param int $page Current page number
 * @param int $limit Records per page
 * @param string $category Optional filter by document category
 * @return array Array with documents and pagination info
 */
function get_vendor_documents($vendor_id, $page = 1, $limit = 10, $category = null) {
    global $db;
    
    $offset = ($page - 1) * $limit;
    
    $query = "
        SELECT d.*, u.name as user_name 
        FROM vendor_documents d
        JOIN users u ON d.user_id = u.id
        WHERE d.vendor_id = ?
    ";
    
    $params = [$vendor_id];
    
    if ($category) {
        $query .= " AND d.category = ?";
        $params[] = $category;
    }
    
    // Count query for pagination
    $count_query = str_replace("d.*, u.name as user_name", "COUNT(*) as total", $query);
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get paginated results
    $query .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
    
    return [
        'documents' => $documents,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ];
}

/**
 * Get document icon class based on file extension
 * 
 * @param string $filename Filename or extension
 * @return string FontAwesome icon class
 */
function get_document_icon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'pdf': 
            return 'fa-file-pdf';
        case 'doc': 
        case 'docx': 
            return 'fa-file-word';
        case 'xls': 
        case 'xlsx': 
            return 'fa-file-excel';
        case 'ppt': 
        case 'pptx': 
            return 'fa-file-powerpoint';
        case 'jpg': 
        case 'jpeg': 
        case 'png': 
        case 'gif': 
        case 'bmp':
            return 'fa-file-image';
        case 'zip': 
        case 'rar': 
        case 'tar': 
        case 'gz':
            return 'fa-file-archive';
        case 'txt': 
            return 'fa-file-alt';
        case 'html': 
        case 'htm': 
        case 'css': 
        case 'js':
            return 'fa-file-code';
        case 'mp4': 
        case 'avi': 
        case 'mov': 
        case 'wmv':
            return 'fa-file-video';
        case 'mp3': 
        case 'wav': 
        case 'ogg':
            return 'fa-file-audio';
        default: 
            return 'fa-file';
    }
}

/**
 * Format file size in human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function format_file_size($bytes) {
    if ($bytes < 1024) {
        return $bytes . " bytes";
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 2) . " KB";
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 2) . " MB";
    } else {
        return round($bytes / 1073741824, 2) . " GB";
    }
}

/**
 * Get available vendor filters for dashboard widgets
 *
 * @return array List of filters
 */
function get_vendor_filters() {
    return [
        'priority' => [
            'high' => 'High Priority',
            'medium' => 'Medium Priority',
            'low' => 'Low Priority'
        ],
        'status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'potential' => 'Potential',
            'blacklisted' => 'Blacklisted'
        ]
    ];
}

/**
 * Get vendor statistics for dashboard
 *
 * @return array Statistics
 */
function get_vendor_statistics() {
    global $db;
    
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'by_priority' => [
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ],
        'by_status' => [
            'active' => 0,
            'inactive' => 0,
            'potential' => 0,
            'blacklisted' => 0
        ],
        'recent' => []
    ];
    
    try {
        // Total vendors
        $stmt = $db->query("SELECT COUNT(*) FROM vendors");
        $stats['total'] = $stmt->fetchColumn();
        
        // Active/inactive vendors
        $stmt = $db->query("SELECT COUNT(*) FROM vendors WHERE is_active = 1");
        $stats['active'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) FROM vendors WHERE is_active = 0");
        $stats['inactive'] = $stmt->fetchColumn();
        
        // Vendors by priority
        $stmt = $db->query("SELECT priority, COUNT(*) as count FROM vendors GROUP BY priority");
        while ($row = $stmt->fetch()) {
            $stats['by_priority'][$row['priority']] = $row['count'];
        }
        
        // Vendors by status
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM vendors GROUP BY status");
        while ($row = $stmt->fetch()) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Recent vendors
        $stmt = $db->query("
            SELECT id, name, created_at, status, priority 
            FROM vendors 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stats['recent'] = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        // Table might not exist yet
    }
    
    return $stats;
}

/**
 * Get pending communications for dashboard
 *
 * @param int $user_id User ID (optional)
 * @return array Pending communications
 */
function get_pending_communications($user_id = null) {
    global $db;
    $pending = [];
    
    try {
        $query = "
            SELECT c.id, c.subject, c.followup_date, c.vendor_id, v.name as vendor_name
            FROM vendor_communications c
            JOIN vendors v ON c.vendor_id = v.id
            WHERE c.status = 'pending' 
              AND c.followup_date IS NOT NULL
              AND c.followup_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ";
        
        $params = [];
        
        if ($user_id) {
            $query .= " AND c.user_id = ?";
            $params[] = $user_id;
        }
        
        $query .= " ORDER BY c.followup_date ASC LIMIT 10";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $pending = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table might not exist yet
    }
    
    return $pending;
}

/**
 * Check if the current user is an admin
 *
 * @return bool True if user is an admin
 */
function is_admin() {
    // Check if user is logged in and has admin role
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // If user is owner, they are admin
    if (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
        return true;
    }
    
    // Check if user has admin role
    if (isset($_SESSION['user_roles']) && is_array($_SESSION['user_roles'])) {
        return in_array('admin', $_SESSION['user_roles']);
    }
    
    return false;
}

/**
 * Get all vendor tabs with their attributes
 * 
 * Retrieves all vendor tabs and their associated attributes in a structured array
 *
 * @return array Array of tabs with their attributes
 */
function get_vendor_tabs_with_attributes() {
    global $db;
    
    $tabs_with_attributes = [];
    
    try {
        // First get all tabs
        $tab_query = $db->query("
            SELECT * FROM vendor_tabs 
            WHERE is_active = 1 
            ORDER BY display_order
        ");
        
        $tabs = $tab_query->fetchAll();
        
        // For each tab, get its attributes
        foreach ($tabs as $tab) {
            $attr_query = $db->prepare("
                SELECT * FROM vendor_attributes_def  
                WHERE tab_id = ? AND is_active = 1 
                ORDER BY display_order
            ");
            
            $attr_query->execute([$tab['id']]);
            $attributes = $attr_query->fetchAll();
            
            // Add this tab with its attributes to the result
            $tabs_with_attributes[] = [
                'tab' => $tab,
                'attributes' => $attributes
            ];
        }
        
        return $tabs_with_attributes;
    } catch (PDOException $e) {
        error_log("Error fetching vendor tabs with attributes: " . $e->getMessage());
        return [];
    }
}
?>
