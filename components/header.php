<?php

/**
 * AbroadWorks Management System
 * 
 * @author ikinciadam@gmail.com
 */

if (!isset($root_dir)) {
    $root_dir = dirname(__DIR__);
}

// Include maintenance check function if not already included
if (!function_exists('is_in_maintenance_mode')) {
    require_once $root_dir . '/includes/maintenance.php';
}

if (!isset($page_title)) {
    $page_title = 'AbroadWorks Management';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo htmlspecialchars(get_setting('site_name', 'AbroadWorks Management')); ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">

    <!-- jQuery UI CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($root_path) ? $root_path : ''; ?>assets/css/style.css">

    <!-- Custom styles -->
    <link rel="stylesheet" href="<?php echo isset($root_path) ? $root_path : ''; ?>assets/css/custom.css">

    <!-- Icon Picker CSS -->
    <link rel="stylesheet" href="<?php echo isset($root_path) ? $root_path : ''; ?>assets/css/icon-picker.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>


    <!-- Favicon -->
    <link rel="icon" href="<?php echo isset($root_path) ? $root_path : ''; ?>assets/images/favicon.ico" type="image/x-icon">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Top Navigation Bar -->
        <nav class="main-header navbar navbar-expand navbar-light bg-white">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="pushmenu" href="#" role="button" id="toggleSidebarBtn">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ms-auto">
                <?php
                // Get maintenance mode status
                $maintenance_mode = false;
                try {
                    $maintenance_mode = is_in_maintenance_mode();
                } catch (Exception $e) {
                    // Function might not exist yet, ignore
                }

                // Get unread notifications and messages count
                $unread_messages = 0;
                $unread_notifications = 0;
                try {
                    // Check if notifications module is active
                    $stmt = $db->prepare("SELECT is_active FROM modules WHERE code = 'notifications'");
                    $stmt->execute();
                    $notifications_active = $stmt->fetchColumn();

                    if ($notifications_active && file_exists($root_dir . '/modules/notifications/models/Message.php') && file_exists($root_dir . '/modules/notifications/models/Notification.php')) {
                        require_once $root_dir . '/modules/notifications/models/Message.php';
                        require_once $root_dir . '/modules/notifications/models/Notification.php';

                        $messageModel = new Message();
                        $notificationModel = new Notification();

                        $unread_messages = $messageModel->getUnreadCount($_SESSION['user_id']);
                        $unread_notifications = $notificationModel->getUnreadCount($_SESSION['user_id']);
                    }
                } catch (Exception $e) {
                    // Ignore errors
                }

                // Display messages icon
                if (isset($notifications_active) && $notifications_active):
                ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" id="messagesDropdown">
                            <i class="fas fa-envelope"></i>
                            <?php if ($unread_messages > 0): ?>
                                <span class="badge bg-warning navbar-badge"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" aria-labelledby="messagesDropdown">
                            <span class="dropdown-item dropdown-header"><?php echo $unread_messages; ?> Messages</span>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo isset($root_path) ? $root_path : ''; ?>modules/notifications/?tab=inbox" class="dropdown-item">
                                <i class="fas fa-inbox me-2"></i> Inbox
                                <?php if ($unread_messages > 0): ?>
                                    <span class="float-end text-warning"><?php echo $unread_messages; ?> new</span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo isset($root_path) ? $root_path : ''; ?>modules/notifications/?tab=sent" class="dropdown-item">
                                <i class="fas fa-paper-plane me-2"></i> Sent Messages
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo isset($root_path) ? $root_path : ''; ?>modules/notifications/?tab=compose" class="dropdown-item">
                                <i class="fas fa-pen me-2"></i> Compose New Message
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo isset($root_path) ? $root_path : ''; ?>modules/notifications/?tab=inbox" class="dropdown-item dropdown-footer">See All Messages</a>
                        </div>
                    </li>

                    <!-- Display notifications icon -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false" id="notificationsDropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="badge bg-danger navbar-badge"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" aria-labelledby="notificationsDropdown">
                            <span class="dropdown-item dropdown-header"><?php echo $unread_notifications; ?> Notifications</span>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo isset($root_path) ? $root_path : ''; ?>modules/notifications/?tab=notifications" class="dropdown-item">
                                <i class="fas fa-bell me-2"></i> System Notifications
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="float-end text-danger"><?php echo $unread_notifications; ?> new</span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo isset($root_path) ? $root_path : ''; ?>modules/notifications/?tab=notifications" class="dropdown-item dropdown-footer">See All Notifications</a>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- Display maintenance mode indicator for admins -->
                <?php if ($maintenance_mode && has_permission('settings-manage')): ?>
                    <li class="nav-item">
                        <a href="<?php echo isset($root_path) ? $root_path : ''; ?>settings.php" class="nav-link text-warning" title="System is in Maintenance Mode">
                            <i class="fas fa-tools"></i>
                            <span class="d-none d-sm-inline-block ms-1">Maintenance Mode</span>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- User Dropdown Menu -->
                <li class="nav-item d-flex align-items-center ms-3">
                    <?php
                    // Get current user's profile image from database (not session)
                    $profile_image = (isset($root_path) ? $root_path : '') . 'assets/images/default-avatar.png';
                    if (isset($_SESSION['user_id'])) {
                        $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $current_user = $stmt->fetch();
                        $user_profile_image = $current_user['profile_image'] ?? '';

                        
                        if (!empty($user_profile_image)) {
                            $profile_image = (isset($root_path) ? $root_path : '') . $user_profile_image;
                        }
                    }
                    ?>
                    <img src="<?php echo $profile_image; ?>" class="user-image img-circle me-2" alt="User Image" style="width:32px;height:32px;">
                    <span class="me-2"><?php echo $_SESSION['user_name'] ?? 'User'; ?></span>
                    <a href="<?php echo isset($root_path) ? $root_path : ''; ?>profile.php" class="btn btn-light btn-sm me-2">Profile</a>
                    <a href="<?php echo isset($root_path) ? $root_path : ''; ?>logout.php" class="btn btn-danger btn-sm">Sign out</a>
                </li>
            </ul>
        </nav>

        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar is included here (sidebar.php) -->