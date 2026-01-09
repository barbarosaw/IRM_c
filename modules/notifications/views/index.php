<?php
/**
 * Notifications Module - Main View
 */

// Start output buffering to prevent headers already sent error
ob_start();

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    define('AW_SYSTEM', true);
}

// Debug log for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    error_log('AJAX request received in notifications module');
    if (isset($_POST['action'])) {
        error_log('Action: ' . $_POST['action']);
    }
}

// Load models
require_once 'models/Message.php';
require_once 'models/Notification.php';

// Initialize models
$messageModel = new Message();
$notificationModel = new Notification();

// Get user ID
$user_id = $_SESSION['user_id'];

// Get unread counts
$unread_messages = $messageModel->getUnreadCount($user_id);
$unread_notifications = $notificationModel->getUnreadCount($user_id);

// Get current tab
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'inbox';

// Get page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'mark_all_read') {
        if ($current_tab === 'inbox' || $current_tab === 'sent') {
            $messageModel->markAllAsRead($user_id);
        } else {
            $notificationModel->markAllAsRead($user_id);
        }
        
        // Set response for AJAX
        $response = [
            'success' => true,
            'redirect' => '../../modules/notifications/index.php?tab=inbox&status=success&message=All+messages+marked+as+read'
        ];
        
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            // Fallback to traditional redirect
            header('Location: ../../modules/notifications/index.php?tab=inbox&status=success&message=All+messages+marked+as+read');
            exit;
        }
    } elseif ($action === 'delete_message' && isset($_POST['message_id'])) {
        $message_id = (int)$_POST['message_id'];
        $messageModel->deleteMessage($message_id, $user_id);
        
        // Set response for AJAX
        $response = [
            'success' => true,
            'redirect' => '../../modules/notifications/index.php?tab=' . $current_tab . '&status=success&message=Message+deleted+successfully'
        ];
        
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            // Fallback to traditional redirect
            header('Location: ../../modules/notifications/index.php?tab=' . $current_tab . '&status=success&message=Message+deleted+successfully');
            exit;
        }
    } elseif ($action === 'delete_notification' && isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        $notificationModel->deleteNotification($notification_id, $user_id);
        
        // Set response for AJAX
        $response = [
            'success' => true,
            'redirect' => '../../modules/notifications/index.php?tab=' . $current_tab . '&status=success&message=Notification+deleted+successfully'
        ];
        
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            // Fallback to traditional redirect
            header('Location: ../../modules/notifications/index.php?tab=' . $current_tab . '&status=success&message=Notification+deleted+successfully');
            exit;
        }
    } elseif ($action === 'send_message' && isset($_POST['receiver_id'], $_POST['subject'], $_POST['message'])) {
        $receiver_id = (int)$_POST['receiver_id'];
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        $forward_id = isset($_POST['forward_id']) ? (int)$_POST['forward_id'] : null;
        
        if (!empty($subject) && !empty($message)) {
            $result = $messageModel->sendMessage($user_id, $receiver_id, $subject, $message, false, $forward_id);
            
        if ($result !== false) {
            // Set response for AJAX
            $response = [
                'success' => true,
                'redirect' => '../../modules/notifications/index.php?tab=inbox&status=success&message=Message+sent+successfully'
            ];
        } else {
                // Set response for AJAX
                $response = [
                    'success' => false,
                    'redirect' => '../../modules/notifications/index.php?tab=compose&status=error&message=Failed+to+send+message'
                ];
            }
        } else {
            // Set response for AJAX
            $response = [
                'success' => false,
                'redirect' => '../../modules/notifications/index.php?tab=compose&status=error&message=Subject+and+message+cannot+be+empty'
            ];
        }
        
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            // Fallback to traditional redirect
            header('Location: ' . $response['redirect']);
            exit;
        }
    } elseif ($action === 'create_notification' && isset($_POST['notification_type'], $_POST['notification_title'], $_POST['notification_message'])) {
        // Check if user is admin, owner or godmode
        if ((isset($_SESSION['is_admin']) && $_SESSION['is_admin']) || 
            (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) || 
            (isset($_SESSION['is_godmode']) && $_SESSION['is_godmode'])) {
            $type = trim($_POST['notification_type']);
            $title = trim($_POST['notification_title']);
            $message = trim($_POST['notification_message']);
            $recipient_type = $_POST['recipient_type'];
            
            if (!empty($type) && !empty($title) && !empty($message)) {
                $user_ids = [];
                
                // If specific users are selected
                if ($recipient_type === 'specific' && isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
                    $user_ids = array_map('intval', $_POST['user_ids']);
                }
                
                // Create notification
                $notification_id = $notificationModel->createNotification(
                    $type,
                    $title,
                    $message,
                    null,
                    null,
                    $user_ids
                );
                
                if ($notification_id !== false) {
                    // Set response for AJAX
                    $response = [
                        'success' => true,
                        'redirect' => '../../modules/notifications/index.php?tab=notifications&status=success&message=Notification+sent+successfully'
                    ];
                } else {
                    // Set response for AJAX
                    $response = [
                        'success' => false,
                        'redirect' => '../../modules/notifications/index.php?tab=create_notification&status=error&message=Failed+to+create+notification'
                    ];
                }
            } else {
                // Set response for AJAX
                $response = [
                    'success' => false,
                    'redirect' => '../../modules/notifications/index.php?tab=create_notification&status=error&message=All+fields+are+required'
                ];
            }
            
            // Check if it's an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            } else {
                // Fallback to traditional redirect
                header('Location: ' . $response['redirect']);
                exit;
            }
        } else {
            header('Location: ../../access-denied.php');
            exit;
        }
    }
}

// Get data based on current tab
$data = [];
$total_pages = 1;

if ($current_tab === 'inbox') {
    $data = $messageModel->getInbox($user_id, $limit, $offset);
    $total_count = count($messageModel->getInbox($user_id, 1000000, 0)); // Not efficient but works for now
    $total_pages = ceil($total_count / $limit);
} elseif ($current_tab === 'sent') {
    $data = $messageModel->getSent($user_id, $limit, $offset);
    $total_count = count($messageModel->getSent($user_id, 1000000, 0)); // Not efficient but works for now
    $total_pages = ceil($total_count / $limit);
} elseif ($current_tab === 'notifications') {
    $data = $notificationModel->getUserNotifications($user_id, $limit, $offset);
    $total_count = count($notificationModel->getUserNotifications($user_id, 1000000, 0)); // Not efficient but works for now
    $total_pages = ceil($total_count / $limit);
} elseif ($current_tab === 'compose') {
    $users = $messageModel->getUsers($user_id);
}

// Get message or notification details
$item_details = null;
if (isset($_GET['message_id'])) {
    $message_id = (int)$_GET['message_id'];
    $item_details = $messageModel->getMessage($message_id, $user_id);
    $current_tab = $item_details && $item_details['sender_id'] == $user_id ? 'sent' : 'inbox';
} elseif (isset($_GET['notification_id'])) {
    $notification_id = (int)$_GET['notification_id'];
    $item_details = $notificationModel->getNotification($notification_id, $user_id);
    $current_tab = 'notifications';
}

// Success message
$success_message = '';
if (isset($_GET['sent']) && $_GET['sent'] == 1) {
    $success_message = 'Message sent successfully.';
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Notifications & Messages</h1>
                </div>

<!-- Include notifications.js -->
<script src="../../assets/js/notifications.js"></script>

<!-- Helper functions for alerts -->
<script>
function showSuccessAlert(title, message, redirectUrl) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../../modules/notifications/';
            }
        });
    } else {
        alert(title + ': ' + message);
        window.location.href = '../../modules/notifications/';
    }
}

function showErrorAlert(title, message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    } else {
        alert(title + ': ' + message);
    }
}
</script>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Notifications</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (isset($_GET['status']) && isset($_GET['message'])): ?>
                <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Folders</h3>
                        </div>
                        <div class="card-body p-0">
                            <ul class="nav nav-pills flex-column">
                                <li class="nav-item">
                                    <a href="index.php?tab=inbox" class="nav-link <?php echo $current_tab === 'inbox' ? 'active' : ''; ?>">
                                        <i class="fas fa-inbox me-2"></i> Inbox
                                        <?php if ($unread_messages > 0): ?>
                                            <span class="badge bg-primary float-end"><?php echo $unread_messages; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="index.php?tab=sent" class="nav-link <?php echo $current_tab === 'sent' ? 'active' : ''; ?>">
                                        <i class="fas fa-paper-plane me-2"></i> Sent
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="index.php?tab=notifications" class="nav-link <?php echo $current_tab === 'notifications' ? 'active' : ''; ?>">
                                        <i class="fas fa-bell me-2"></i> Notifications
                                        <?php if ($unread_notifications > 0): ?>
                                            <span class="badge bg-warning float-end"><?php echo $unread_notifications; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] || 
                                         isset($_SESSION['is_owner']) && $_SESSION['is_owner'] || 
                                         isset($_SESSION['is_godmode']) && $_SESSION['is_godmode']): ?>
                                <li class="nav-item">
                                    <a href="index.php?tab=create_notification" class="nav-link <?php echo $current_tab === 'create_notification' ? 'active' : ''; ?>">
                                        <i class="fas fa-bullhorn me-2"></i> Create System Notification
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="index.php?tab=compose" class="btn btn-primary btn-block">
                            <i class="fas fa-pen me-2"></i> Compose
                        </a>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <?php if ($current_tab === 'compose'): ?>
                        <!-- Compose Message -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Compose New Message</h3>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_GET['sent']) && $_GET['sent'] == 1): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i> Message sent successfully.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="post" action="index.php?tab=compose" id="composeForm">
                                    <input type="hidden" name="action" value="send_message">
                                    
                                    <?php if (isset($_GET['reply_to']) && isset($_GET['message_id'])): ?>
                                        <?php
                                        $reply_to = (int)$_GET['reply_to'];
                                        $original_message_id = (int)$_GET['message_id'];
                                        $original_message = $messageModel->getMessage($original_message_id, $user_id);
                                        ?>
                                        <input type="hidden" name="receiver_id" value="<?php echo $reply_to; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">To:</label>
                                            <p class="form-control-static"><?php echo htmlspecialchars($original_message['sender_name']); ?></p>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Subject:</label>
                                            <p class="form-control-static"><?php echo htmlspecialchars($original_message['subject']); ?></p>
                                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($original_message['subject']); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message:</label>
                                            <textarea class="form-control" id="message" name="message" rows="10" required></textarea>
                                        </div>
                                        
                                        <div class="card mb-3">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">Original Message</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted">
                                                    <small>
                                                        <strong>From:</strong> <?php echo htmlspecialchars($original_message['sender_name']); ?><br>
                                                        <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($original_message['created_at'])); ?><br>
                                                    </small>
                                                </p>
                                                <hr>
                                                <div class="original-message">
                                                    <?php echo nl2br(htmlspecialchars($original_message['message'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif (isset($_GET['forward_id'])): ?>
                                        <?php
                                        $forward_id = (int)$_GET['forward_id'];
                                        $forward_message = $messageModel->getMessage($forward_id, $user_id);
                                        ?>
                                        <input type="hidden" name="forward_id" value="<?php echo $forward_id; ?>">
                                        <div class="mb-3">
                                            <label for="receiver_id" class="form-label">To:</label>
                                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                                <option value="">Select recipient</option>
                                                <?php foreach ($users as $user_item): ?>
                                                    <option value="<?php echo $user_item['id']; ?>"><?php echo htmlspecialchars($user_item['name']); ?> (<?php echo htmlspecialchars($user_item['email']); ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="subject" class="form-label">Subject:</label>
                                            <input type="text" class="form-control" id="subject" name="subject" value="Fwd: <?php echo htmlspecialchars($forward_message['subject']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message:</label>
                                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                        </div>
                                        
                                        <div class="card mb-3">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">Forwarded Message</h6>
                                            </div>
                                            <div class="card-body">
                                                <p class="text-muted">
                                                    <small>
                                                        <strong>From:</strong> <?php echo htmlspecialchars($forward_message['sender_name']); ?><br>
                                                        <strong>To:</strong> <?php echo htmlspecialchars($forward_message['receiver_name']); ?><br>
                                                        <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($forward_message['created_at'])); ?><br>
                                                        <strong>Subject:</strong> <?php echo htmlspecialchars($forward_message['subject']); ?>
                                                    </small>
                                                </p>
                                                <hr>
                                                <div class="forwarded-message">
                                                    <?php echo nl2br(htmlspecialchars($forward_message['message'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <label for="receiver_id" class="form-label">To:</label>
                                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                                <option value="">Select recipient</option>
                                                <?php foreach ($users as $user_item): ?>
                                                    <option value="<?php echo $user_item['id']; ?>"><?php echo htmlspecialchars($user_item['name']); ?> (<?php echo htmlspecialchars($user_item['email']); ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="subject" class="form-label">Subject:</label>
                                            <input type="text" class="form-control" id="subject" name="subject" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message:</label>
                                            <textarea class="form-control" id="message" name="message" rows="10" required></textarea>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary" id="sendButton">
                                            <i class="fas fa-paper-plane me-2"></i> Send Message
                                        </button>
                                        <a href="index.php?tab=inbox" class="btn btn-secondary ms-2">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <script>
                        document.getElementById('composeForm').addEventListener('submit', function(e) {
                            // Show loading indicator
                            const sendButton = document.getElementById('sendButton');
                            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
                            sendButton.disabled = true;
                        });
                        </script>
                    <?php elseif ($item_details): ?>
                        <!-- Message or Notification Details -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php if (isset($_GET['message_id'])): ?>
                                        <?php echo htmlspecialchars($item_details['subject']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($item_details['title']); ?>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_GET['message_id'])): ?>
                                    <!-- Message Details -->
                                    <div class="mb-3">
                                        <strong>From:</strong> 
                                        <?php if ($item_details['is_system_user']): ?>
                                            <span class="badge bg-info">System</span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($item_details['sender_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>To:</strong> <?php echo htmlspecialchars($item_details['receiver_name']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($item_details['created_at'])); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Subject:</strong> <?php echo htmlspecialchars($item_details['subject']); ?>
                                    </div>
                                    <hr>
                                    <div class="message-body">
                                        <?php echo nl2br(htmlspecialchars($item_details['message'])); ?>
                                    </div>
                                    
                                    <div class="mt-4 d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php if ($item_details['sender_id'] != $user_id && $item_details['is_system_user'] != 1): ?>
                                                <a href="index.php?tab=compose&reply_to=<?php echo $item_details['sender_id']; ?>&message_id=<?php echo $item_details['id']; ?>" class="btn btn-primary">
                                                    <i class="fas fa-reply me-2"></i> Reply
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="index.php?tab=compose&forward_id=<?php echo $item_details['id']; ?>" class="btn btn-info ms-2">
                                                <i class="fas fa-share me-2"></i> Forward
                                            </a>
                                            
                                            <a href="index.php?tab=<?php echo $current_tab; ?>" class="btn btn-secondary ms-2">
                                                <i class="fas fa-arrow-left me-2"></i> Back
                                            </a>
                                        </div>
                                        
                                        <div>
                                            <span class="badge bg-<?php echo $item_details['is_read'] ? 'success' : 'warning'; ?> me-2">
                                                <?php echo $item_details['is_read'] ? 'Read' : 'Unread'; ?>
                                                <?php if ($item_details['is_read'] && $item_details['read_at']): ?>
                                                    <small>(<?php echo date('M d, H:i', strtotime($item_details['read_at'])); ?>)</small>
                                                <?php endif; ?>
                                            </span>
                                            
                                            <form method="post" action="index.php?tab=<?php echo $current_tab; ?>" class="d-inline">
                                                <input type="hidden" name="action" value="delete_message">
                                                <input type="hidden" name="message_id" value="<?php echo $item_details['id']; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this message?');">
                                                    <i class="fas fa-trash me-2"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Notification Details -->
                                    <div class="mb-3">
                                        <strong>Type:</strong> <?php echo htmlspecialchars($item_details['type']); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($item_details['created_at'])); ?>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Title:</strong> <?php echo htmlspecialchars($item_details['title']); ?>
                                    </div>
                                    <hr>
                                    <div class="notification-body">
                                        <?php echo nl2br(htmlspecialchars($item_details['message'])); ?>
                                    </div>
                                    
                                    <?php if ((isset($_SESSION['is_admin']) && $_SESSION['is_admin']) || 
                                             (isset($_SESSION['is_owner']) && $_SESSION['is_owner']) || 
                                             (isset($_SESSION['is_godmode']) && $_SESSION['is_godmode'])): ?>
                                    <!-- Read Status for Admins -->
                                    <div class="mt-4">
                                        <h5>Read Status</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>User</th>
                                                        <th>Status</th>
                                                        <th>Read At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    // Get read status for this notification
                                                    $read_status = $notificationModel->getNotificationReadStatus($item_details['id']);
                                                    if (!empty($read_status)):
                                                        foreach ($read_status as $status):
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($status['name']); ?></td>
                                                        <td>
                                                            <?php if ($status['is_read']): ?>
                                                                <span class="badge bg-success">Read</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Unread</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $status['read_at'] ? date('M d, Y H:i', strtotime($status['read_at'])) : '-'; ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                        endforeach;
                                                    else:
                                                    ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center">No read status information available</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-4">
                                        <a href="index.php?tab=notifications" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i> Back
                                        </a>
                                        
                                        <form method="post" action="index.php?tab=notifications" class="d-inline">
                                            <input type="hidden" name="action" value="delete_notification">
                                            <input type="hidden" name="notification_id" value="<?php echo $item_details['id']; ?>">
                                            <button type="submit" class="btn btn-danger ms-2" onclick="return confirm('Are you sure you want to delete this notification?');">
                                                <i class="fas fa-trash me-2"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($current_tab === 'create_notification'): ?>
                        <!-- Create System Notification -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Create System Notification</h3>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_GET['notification_sent']) && $_GET['notification_sent'] == 1): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i> Notification sent successfully.
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="post" action="index.php?tab=create_notification" id="notificationForm">
                                    <input type="hidden" name="action" value="create_notification">
                                    
                                    <div class="mb-3">
                                        <label for="notification_type" class="form-label">Notification Type:</label>
                                        <select class="form-select" id="notification_type" name="notification_type" required>
                                            <option value="info">Information</option>
                                            <option value="warning">Warning</option>
                                            <option value="success">Success</option>
                                            <option value="danger">Danger</option>
                                            <option value="system">System</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notification_title" class="form-label">Title:</label>
                                        <input type="text" class="form-control" id="notification_title" name="notification_title" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notification_message" class="form-label">Message:</label>
                                        <textarea class="form-control" id="notification_message" name="notification_message" rows="5" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Recipients:</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipient_type" id="all_users" value="all" checked>
                                            <label class="form-check-label" for="all_users">
                                                All Users
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipient_type" id="specific_users" value="specific">
                                            <label class="form-check-label" for="specific_users">
                                                Specific Users
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3" id="specific_users_container" style="display: none;">
                                        <label for="user_ids" class="form-label">Select Users:</label>
                                        <select class="form-select" id="user_ids" name="user_ids[]" multiple size="5">
                                            <?php
                                            // Get all active users
                                            $stmt = $db->prepare("
                                                SELECT id, name, email 
                                                FROM users 
                                                WHERE is_active = 1 AND is_system = 0
                                                ORDER BY name
                                            ");
                                            $stmt->execute();
                                            $all_users = $stmt->fetchAll();
                                            
                                            foreach ($all_users as $user_item):
                                            ?>
                                                <option value="<?php echo $user_item['id']; ?>">
                                                    <?php echo htmlspecialchars($user_item['name']); ?> (<?php echo htmlspecialchars($user_item['email']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Hold Ctrl (or Cmd on Mac) to select multiple users.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary" id="sendNotificationButton">
                                            <i class="fas fa-paper-plane me-2"></i> Send Notification
                                        </button>
                                        <a href="index.php?tab=notifications" class="btn btn-secondary ms-2">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const recipientTypeRadios = document.querySelectorAll('input[name="recipient_type"]');
                            const specificUsersContainer = document.getElementById('specific_users_container');
                            
                            // Toggle specific users container visibility
                            recipientTypeRadios.forEach(function(radio) {
                                radio.addEventListener('change', function() {
                                    if (this.value === 'specific') {
                                        specificUsersContainer.style.display = 'block';
                                    } else {
                                        specificUsersContainer.style.display = 'none';
                                    }
                                });
                            });
                            
                            // Form submission
                            document.getElementById('notificationForm').addEventListener('submit', function(e) {
                                // Validate specific users selection
                                if (document.getElementById('specific_users').checked) {
                                    const selectedUsers = document.getElementById('user_ids').selectedOptions;
                                    if (selectedUsers.length === 0) {
                                        e.preventDefault();
                                        alert('Please select at least one user.');
                                        return;
                                    }
                                }
                                
                                // Show loading indicator
                                const sendButton = document.getElementById('sendNotificationButton');
                                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
                                sendButton.disabled = true;
                            });
                        });
                        </script>
                    <?php else: ?>
                        <!-- Message or Notification List -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php if ($current_tab === 'inbox'): ?>
                                        Inbox
                                    <?php elseif ($current_tab === 'sent'): ?>
                                        Sent Messages
                                    <?php else: ?>
                                        Notifications
                                    <?php endif; ?>
                                </h3>
                                
                                <div class="card-tools">
                                <form method="post" action="index.php?tab=<?php echo $current_tab; ?>" class="d-inline form-ajax">
                                    <input type="hidden" name="action" value="mark_all_read">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-check-double me-1"></i> Mark All as Read
                                    </button>
                                </form>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive mailbox-messages">
                                    <table class="table table-hover">
                                        <tbody>
                                            <?php if (empty($data)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">
                                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">No messages found.</p>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($data as $item): ?>
                                                    <tr class="<?php echo ($current_tab !== 'sent' && !$item['is_read']) ? 'fw-bold' : ''; ?>">
                                                        <td class="mailbox-name" style="width: 25%;">
                                                            <?php if ($current_tab === 'inbox'): ?>
                                                                <?php if ($item['is_system_user']): ?>
                                                                    <span class="badge bg-info">System</span>
                                                                <?php else: ?>
                                                                    <?php echo htmlspecialchars($item['sender_name']); ?>
                                                                <?php endif; ?>
                                                            <?php elseif ($current_tab === 'sent'): ?>
                                                                To: <?php echo htmlspecialchars($item['receiver_name']); ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-<?php echo $item['type'] === 'activity' ? 'info' : 'warning'; ?>">
                                                                    <?php echo htmlspecialchars($item['type']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="mailbox-subject">
                                                            <?php if ($current_tab === 'notifications'): ?>
                                                                <a href="index.php?notification_id=<?php echo $item['id']; ?>">
                                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="index.php?message_id=<?php echo $item['id']; ?>">
                                                                    <?php echo htmlspecialchars($item['subject']); ?>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="mailbox-date" style="width: 20%;">
                                                            <?php echo date('M d, Y H:i', strtotime($item['created_at'])); ?>
                                                        </td>
                                                        <td class="mailbox-actions" style="width: 10%;">
                                                            <?php if ($current_tab === 'notifications'): ?>
                                                <form method="post" action="index.php?tab=notifications" class="d-inline form-ajax">
                                                    <input type="hidden" name="action" value="delete_notification">
                                                    <input type="hidden" name="notification_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?');">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                            <?php else: ?>
                                                                <form method="post" action="index.php?tab=<?php echo $current_tab; ?>" class="d-inline form-ajax">
                                                                    <input type="hidden" name="action" value="delete_message">
                                                                    <input type="hidden" name="message_id" value="<?php echo $item['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?');">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if ($total_pages > 1): ?>
                                <div class="card-footer">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="index.php?tab=<?php echo $current_tab; ?>&page=<?php echo $i; ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
