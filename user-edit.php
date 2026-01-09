<?php
/**
 * AbroadWorks Management System - Edit User
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user has permission to manage users
check_page_access('users-manage');

$page_title = 'Edit User';
$success_message = '';
$error_message = '';

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get all roles for the dropdown
$roles = $db->query("SELECT * FROM roles ORDER BY name")->fetchAll();

// Get user's current roles
$stmt = $db->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
$stmt->execute([$user_id]);
$selected_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_roles = isset($_POST['roles']) ? $_POST['roles'] : [];
    $change_password = !empty($_POST['password']);
    
    // Validate input
    if (empty($name) || empty($email)) {
        $error_message = "Name and email fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Check if email already exists (excluding current user)
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $error_message = "Email address is already in use by another user.";
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Update user basic info
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $email, $is_active, $user_id]);
                
                // Update password if provided
                if ($change_password) {
                    $password = $_POST['password'];
                    $confirm_password = $_POST['confirm_password'];
                    
                    if (empty($password)) {
                        throw new Exception("Password field is required.");
                    } elseif ($password !== $confirm_password) {
                        throw new Exception("Passwords do not match.");
                    } elseif (strlen($password) < 6) {
                        throw new Exception("Password must be at least 6 characters long.");
                    }
                    
                    $hashed_password = password_hash_safe($password);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                }
                
                // Update user roles (delete existing and add new ones)
                $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                foreach ($new_roles as $role_id) {
                    $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $role_id]);
                }
                
                // Commit transaction
                $db->commit();
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'update', 'user', "Updated user: $name (ID: $user_id)");
                
                $success_message = "User updated successfully!";
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                // Refresh selected roles
                $stmt = $db->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $selected_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                $error_message = "Error updating user: " . $e->getMessage();
            }
        }
    }
}

// Include header and sidebar
require_once 'components/header.php';
require_once 'components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Edit User</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                        <li class="breadcrumb-item active">Edit User</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Edit User: <?php echo htmlspecialchars($user['name']); ?></h3>
                    <a href="user-view.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-eye me-1"></i> View Profile
                    </a>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    <div class="invalid-feedback">Please enter the user's full name.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password" minlength="6">
                                    <small class="form-text text-muted">Leave blank to keep current password.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="roles" class="form-label">Assign Roles</label>
                                    <select class="form-control select2" id="roles" name="roles[]" multiple>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo in_array($role['id'], $selected_roles) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Select one or more roles to assign to the user.</small>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Active User</label>
                                    <small class="form-text text-muted d-block">Inactive users cannot log in to the system.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Other Information</label>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <p class="mb-1"><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></p>
                                            <p class="mb-1"><strong>Last Login:</strong> <?php echo $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : 'Never'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="users.php" class="btn btn-secondary ms-2">Back to User List</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password match validation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (password.value && password.value != confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    password.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);
});
</script>

<?php require_once 'components/footer.php'; ?>
