<?php
/**
 * AbroadWorks Management System - User Profile
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/two_factor.php';

// Check if user is logged in
check_page_access();

$page_title = 'Profile';
$success_message = '';
$error_message = '';
$user_id = $_SESSION['user_id'];

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user roles
$stmt = $db->prepare("
    SELECT r.*
    FROM roles r
    JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = ?
");
$stmt->execute([$user_id]);
$user_roles = $stmt->fetchAll();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    
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
            // Update user basic info
            $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            
            try {
                $stmt->execute([$name, $email, $user_id]);
                
                // Update session data
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                // Log the activity
                log_activity($user_id, 'update', 'profile', "Updated profile information");
                
                $success_message = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } else {
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect.";
        } else {
            // Update password
            $hashed_password = password_hash_safe($new_password);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            
            try {
                $stmt->execute([$hashed_password, $user_id]);
                
                // Log the activity
                log_activity($user_id, 'update', 'password', "Changed password");
                
                $success_message = "Password updated successfully!";
            } catch (PDOException $e) {
                $error_message = "Error updating password: " . $e->getMessage();
            }
        }
    }
}

// Handle 2FA actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'enable_2fa') {
        $secret = clean_input($_POST['secret']);
        $verification_code = clean_input($_POST['verification_code']);
        
        // Verify the code
        if (verify_2fa_code($secret, $verification_code)) {
            // Enable 2FA for the user
            if (enable_2fa_for_user($user_id, $secret)) {
                $success_message = "Two-factor authentication enabled successfully!";
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error_message = "An error occurred while enabling two-factor authentication.";
            }
        } else {
            $error_message = "Invalid verification code. Please try again.";
        }
    } elseif ($action === 'disable_2fa') {
        // Disable 2FA for the user
        if (disable_2fa_for_user($user_id)) {
            $success_message = "Two-factor authentication has been disabled.";
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } else {
            $error_message = "An error occurred while disabling two-factor authentication.";
        }
    } elseif ($action === 'generate_recovery_codes') {
        // Generate new recovery codes
        $codes = generate_recovery_codes($user_id);
        if ($codes) {
            $success_message = "New recovery codes have been generated.";
        } else {
            $error_message = "An error occurred while generating recovery codes.";
        }
    }
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_avatar'])) {
    // Check if file was uploaded without errors
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        // Validate file
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $error_message = "Only JPEG, PNG, and GIF images are allowed.";
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            $error_message = "File size must be less than 2MB.";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = 'assets/uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate a secure unique filename
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'user_' . $user_id . '_' . uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            // Delete old profile image if exists
            if (!empty($user['profile_image']) && $user['profile_image'] != 'assets/images/default-avatar.png') {
                if (file_exists($user['profile_image'])) {
                    @unlink($user['profile_image']);
                }
            }
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                try {
                    // Save the full path to the database
                    $profile_image_path = $target_file; // Full path relative to root
                    
                    // Update user's profile_image in the database
                    $stmt = $db->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$profile_image_path, $_SESSION['user_id']]);
                    
                    // Update session if needed
                    $_SESSION['profile_image'] = $profile_image_path;
                    
                    $success_message = "Avatar uploaded successfully!";
                    
                    // Log activity
                    log_activity($_SESSION['user_id'], 'update', 'avatar', "Uploaded new avatar");
                    
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                } catch (PDOException $e) {
                    $error_message = "Database error: " . $e->getMessage();
                    // Delete the uploaded file if database update fails
                    unlink($target_file);
                }
            } else {
                $error_message = "Error uploading file.";
            }
        }
    } else {
        $error_message = "No file uploaded or upload error.";
    }
}

// Handle random avatar selection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['random_avatar'])) {
    $avatars_dir = 'assets/images/avatars/';
    
    // Get all avatar files
    $avatar_files = glob($avatars_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    
    if (count($avatar_files) > 0) {
        // Select a random avatar
        $random_avatar = $avatar_files[array_rand($avatar_files)];
        
        try {
            // Delete old profile image if exists and it's an uploaded file (not a system avatar)
            if (!empty($user['profile_image']) && 
                $user['profile_image'] != 'assets/images/default-avatar.png' &&
                !str_contains($user['profile_image'], 'assets/images/avatars/') &&
                file_exists($user['profile_image'])) {
                @unlink($user['profile_image']);
            }
            
            // Update the database with the full path
            $stmt = $db->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$random_avatar, $user_id]);
            
            // Log the activity
            log_activity($user_id, 'update', 'profile', "Selected random avatar");
            
            $success_message = "Random avatar selected successfully!";
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error_message = "Error updating profile image: " . $e->getMessage();
        }
    } else {
        $error_message = "No avatar images found. Please upload some avatar images to the assets/images/avatars directory.";
    }
}

// Handle avatar selection from grid
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['select_avatar'])) {
    $selected_avatar = clean_input($_POST['selected_avatar']);
    
    // Validate the selected avatar exists and is in the avatars directory
    if (file_exists($selected_avatar) && str_contains($selected_avatar, 'assets/images/avatars/')) {
        try {
            // Delete old profile image if exists and it's an uploaded file (not a system avatar)
            if (!empty($user['profile_image']) && 
                $user['profile_image'] != 'assets/images/default-avatar.png' &&
                !str_contains($user['profile_image'], 'assets/images/avatars/') &&
                file_exists($user['profile_image'])) {
                @unlink($user['profile_image']);
            }
            
            // Update the database
            $stmt = $db->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$selected_avatar, $user_id]);
            
            // Log the activity
            log_activity($user_id, 'update', 'profile', "Selected avatar: " . basename($selected_avatar));
            
            $success_message = "Avatar updated successfully!";
            
            // Refresh user data
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error_message = "Error updating avatar: " . $e->getMessage();
        }
    } else {
        $error_message = "Invalid avatar selected.";
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
                    <h1 class="m-0 text-primary">My Profile</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Profile</li>
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
            
            <div class="row">
                <div class="col-md-4">
                    <!-- Profile Image -->
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img class="profile-user-img img-fluid img-circle" 
                                         src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                         alt="User profile picture"
                                         style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <img class="profile-user-img img-fluid img-circle" 
                                         src="assets/images/default-avatar.png" 
                                         alt="User profile picture"
                                         style="width: 150px; height: 150px; object-fit: cover;">
                                <?php endif; ?>
                            </div>

                            <h3 class="profile-username text-center"><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p class="text-muted text-center">
                                <?php echo implode(', ', array_column($user_roles, 'name')); ?>
                            </p>

                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b>Email</b> <span class="float-end"><?php echo htmlspecialchars($user['email']); ?></span>
                                </li>
                                <li class="list-group-item">
                                    <b>Member Since</b> <span class="float-end"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                </li>
                                <li class="list-group-item">
                                    <b>Last Login</b> <span class="float-end"><?php echo $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : 'Never'; ?></span>
                                </li>
                                <?php if (isset($user['two_factor_enabled']) && $user['two_factor_enabled']): ?>
                                <li class="list-group-item">
                                    <b>Two-Factor Auth</b> <span class="float-end text-success"><i class="fas fa-check-circle"></i> Enabled</span>
                                </li>
                                <?php endif; ?>
                            </ul>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-primary flex-grow-1 me-2" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                    <i class="fas fa-camera me-1"></i> Upload Photo
                                </button>
                                
                                <form method="post" class="flex-grow-1">
                                    <button type="submit" name="random_avatar" class="btn btn-info w-100">
                                        <i class="fas fa-random me-1"></i> Random Avatar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Avatar Selection Card -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-images me-2"></i>Choose Avatar
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Select a pre-made avatar from our collection:</p>
                            <div class="row g-2" id="avatarGrid">
                                <?php
                                $avatars_dir = 'assets/images/avatars/';
                                $avatar_files = glob($avatars_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
                                
                                if (count($avatar_files) > 0):
                                    foreach ($avatar_files as $index => $avatar):
                                        $avatar_name = basename($avatar);
                                        $is_current = ($user['profile_image'] == $avatar);
                                ?>
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <div class="avatar-option <?= $is_current ? 'selected' : '' ?>" 
                                         data-avatar="<?= htmlspecialchars($avatar) ?>"
                                         style="cursor: pointer; position: relative;">
                                        <img src="<?= htmlspecialchars($avatar) ?>" 
                                             alt="Avatar <?= $index + 1 ?>" 
                                             class="img-fluid rounded border"
                                             style="aspect-ratio: 1; object-fit: cover; transition: all 0.3s ease;">
                                        <?php if ($is_current): ?>
                                        <div class="position-absolute top-0 end-0 bg-success text-white rounded-circle" 
                                             style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; margin: 5px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No avatar images found. Please upload some avatar images to the <code>assets/images/avatars/</code> directory.
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Hidden form for avatar selection -->
                            <form method="post" id="avatarSelectionForm" style="display: none;">
                                <input type="hidden" name="selected_avatar" id="selectedAvatarInput">
                                <input type="hidden" name="select_avatar" value="1">
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <!-- Profile Settings -->
                    <div class="card">
                        <div class="card-header p-2">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#profile" role="tab">Profile</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#password" role="tab">Change Password</a>
                                </li>
                                <?php if (is_2fa_enabled()): ?>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#two_factor" role="tab">Two-Factor Authentication</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="tab-pane active" id="profile">
                                    <form method="post" class="needs-validation" novalidate>
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                            <div class="invalid-feedback">Please enter your full name.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            <div class="invalid-feedback">Please enter a valid email address.</div>
                                        </div>
                                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                    </form>
                                </div>
                                
                                <div class="tab-pane" id="password">
                                    <form method="post" class="needs-validation" novalidate>
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            <div class="invalid-feedback">Please enter your current password.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                                            <div class="invalid-feedback">Password must be at least 6 characters long.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <div class="invalid-feedback">Passwords must match.</div>
                                        </div>
                                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                    </form>
                                </div>
                                
<?php if (is_2fa_enabled() && (!isset($user['is_owner']) || !$user['is_owner'])): ?>
                                <div class="tab-pane" id="two_factor">
                                    <h5 class="mb-4">Two-Factor Authentication (2FA)</h5>
                                    
                                    <?php
                                    // Check if 2FA is already enabled for this user
                                    $two_factor_enabled = is_2fa_enabled_for_user($user_id);
                                    $two_factor_secret = get_user_2fa_secret($user_id);
                                    
                                    // Generate a new secret if not enabled yet
                                    if (!$two_factor_enabled || empty($two_factor_secret)) {
                                        $two_factor_secret = generate_2fa_secret();
                                    }
                                    
                                    // Generate QR code URL
                                    $qr_url = generate_2fa_qr_url($user['email'], $two_factor_secret);
                                    ?>
                                    
                                    <?php if ($two_factor_enabled): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-shield-alt me-2"></i> Two-factor authentication is enabled for your account.
                                        </div>
                                        
                                        <form method="post" action="">
                                            <input type="hidden" name="action" value="disable_2fa">
                                            <p>Disabling two-factor authentication will reduce the security of your account.</p>
                                            <button type="submit" name="disable_2fa" class="btn btn-danger" onclick="return confirm('Are you sure you want to disable two-factor authentication? This will reduce the security of your account.');">
                                                <i class="fas fa-times me-2"></i> Disable Two-Factor Authentication
                                            </button>
                                        </form>
                                        
                                        <hr>
                                        
                                        <h6 class="mt-4">Recovery Codes</h6>
                                        <p>Store these codes in a secure place. You can use these codes to access your account if you lose access to your phone.</p>
                                        
                                        <form method="post" action="">
                                            <input type="hidden" name="action" value="generate_recovery_codes">
                                            <button type="submit" name="generate_recovery_codes" class="btn btn-warning mb-3">
                                                <i class="fas fa-sync me-2"></i> Generate New Recovery Codes
                                            </button>
                                        </form>
                                        
                                        <?php
                                        // Get recovery codes
                                        $stmt = $db->prepare("
                                            SELECT code FROM two_factor_recovery_codes 
                                            WHERE user_id = ? AND is_used = 0
                                            ORDER BY created_at DESC
                                        ");
                                        $stmt->execute([$user_id]);
                                        $recovery_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                        
                                        if (count($recovery_codes) > 0):
                                        ?>
                                            <div class="row">
                                                <?php foreach ($recovery_codes as $code): ?>
                                                <div class="col-md-6 mb-2">
                                                    <code class="bg-light p-2 d-block"><?php echo $code; ?></code>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning">
                                                You don't have any recovery codes yet. Use the button above to generate new codes.
                                            </div>
                                        <?php endif; ?>
                                        
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i> Two-factor authentication is currently disabled.
                                        </div>
                                        
                                        <p>Two-factor authentication adds an extra layer of security to your account. When enabled, you'll need to enter a code from an app on your phone in addition to your password when logging in.</p>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>1. Install Google Authenticator app</h6>
                                                <p>Install an authenticator app like Google Authenticator, Microsoft Authenticator, or Authy on your phone.</p>
                                                
                                                <div class="d-flex mb-3">
                                                    <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" class="btn btn-outline-secondary me-2">
                                                        <i class="fab fa-android me-1"></i> Android
                                                    </a>
                                                    <a href="https://apps.apple.com/us/app/google-authenticator/id388497605" target="_blank" class="btn btn-outline-secondary">
                                                        <i class="fab fa-apple me-1"></i> iOS
                                                    </a>
                                                </div>
                                                
                                                <h6>2. Scan the QR code</h6>
                                                <p>Open the app and scan this QR code:</p>
                                                
                                                <div class="text-center mb-3">
                                                    <img src="<?php echo $qr_url; ?>" alt="QR Code" class="img-fluid border p-2" style="max-width: 200px;">
                                                </div>
                                                
                                                <p>Can't scan the QR code? Enter this code manually:</p>
                                                <div class="bg-light p-2 mb-3">
                                                    <code><?php echo $two_factor_secret; ?></code>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <h6>3. Enter verification code</h6>
                                                <p>Enter the 6-digit code shown in the app:</p>
                                                
                                                <form method="post" action="">
                                                    <input type="hidden" name="action" value="enable_2fa">
                                                    <input type="hidden" name="secret" value="<?php echo $two_factor_secret; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <input type="text" class="form-control" name="verification_code" placeholder="6-digit code" required>
                                                    </div>
                                                    
                                                    <div class="d-grid">
                                                        <button type="submit" name="enable_2fa" class="btn btn-primary">
                                                            <i class="fas fa-shield-alt me-2"></i> Enable Two-Factor Authentication
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Login History -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="fas fa-history me-2"></i>Login History
                            </h5>
                            <small class="text-muted">&nbsp;Recent login activities (last 20 logins)</small>
                        </div>
                        <div class="card-body">
                            <?php
                            $stmt = $db->prepare("
                                SELECT 
                                    ip_address,
                                    user_agent,
                                    last_activity,
                                    expired,
                                    CASE 
                                        WHEN expired = 0 THEN 'Active'
                                        ELSE 'Expired'
                                    END as status
                                FROM sessions 
                                WHERE user_id = ?
                                ORDER BY last_activity DESC
                                LIMIT 20
                            ");
                            $stmt->execute([$user_id]);
                            $login_history = $stmt->fetchAll();
                            ?>
                            
                            <?php if (count($login_history) > 0): ?>
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-striped table-hover table-sm">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th><i class="fas fa-globe me-1"></i>IP Address</th>
                                                <th><i class="fas fa-desktop me-1"></i>Device & Browser</th>
                                                <th><i class="fas fa-clock me-1"></i>Login Time</th>
                                                <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($login_history as $login): ?>
                                                <tr style="line-height: 1.2;">
                                                    <td class="align-middle">
                                                        <span class="badge bg-secondary small"><?php echo htmlspecialchars($login['ip_address'] ?? 'Unknown'); ?></span>
                                                    </td>
                                                    <td class="align-middle">
                                                        <?php
                                                        $ua = $login['user_agent'] ?? '';
                                                        $browser = "";
                                                        $browser_icon = "fas fa-globe";
                                                        
                                                        if (strpos($ua, 'Chrome') !== false) {
                                                            $browser = "Chrome";
                                                            $browser_icon = "fab fa-chrome";
                                                        } elseif (strpos($ua, 'Firefox') !== false) {
                                                            $browser = "Firefox";
                                                            $browser_icon = "fab fa-firefox";
                                                        } elseif (strpos($ua, 'Safari') !== false) {
                                                            $browser = "Safari";
                                                            $browser_icon = "fab fa-safari";
                                                        } elseif (strpos($ua, 'Edge') !== false) {
                                                            $browser = "Edge";
                                                            $browser_icon = "fab fa-edge";
                                                        } elseif (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident/') !== false) {
                                                            $browser = "Internet Explorer";
                                                            $browser_icon = "fab fa-internet-explorer";
                                                        } else {
                                                            $browser = "Unknown Browser";
                                                        }
                                                        
                                                        $device = "";
                                                        $device_icon = "fas fa-desktop";
                                                        if (strpos($ua, 'Mobile') !== false) {
                                                            $device = "Mobile";
                                                            $device_icon = "fas fa-mobile-alt";
                                                        } elseif (strpos($ua, 'Tablet') !== false) {
                                                            $device = "Tablet";
                                                            $device_icon = "fas fa-tablet-alt";
                                                        } else {
                                                            $device = "Desktop";
                                                        }
                                                        ?>
                                                        <div class="mb-1">
                                                            <i class="<?php echo $browser_icon; ?> text-primary me-1"></i>
                                                            <small><strong><?php echo $browser; ?></strong></small>
                                                        </div>
                                                        <div class="text-muted" style="font-size: 0.75rem;">
                                                            <i class="<?php echo $device_icon; ?> me-1"></i>
                                                            <?php echo $device; ?>
                                                        </div>
                                                    </td>
                                                    <td class="align-middle">
                                                        <div class="small"><?php echo date('d M Y', strtotime($login['last_activity'])); ?></div>
                                                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('H:i:s', strtotime($login['last_activity'])); ?></div>
                                                    </td>
                                                    <td class="align-middle">
                                                        <?php if ($login['status'] == 'Active'): ?>
                                                            <span class="badge bg-success small">
                                                                <i class="fas fa-circle me-1"></i>Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary small">
                                                                <i class="fas fa-times-circle me-1"></i>Expired
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Statistics -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="small-box bg-info">
                                            <div class="inner">
                                                <h3>
                                                    <?php 
                                                    $active_sessions = array_filter($login_history, function($login) {
                                                        return $login['status'] == 'Active';
                                                    });
                                                    echo count($active_sessions);
                                                    ?>
                                                </h3>
                                                <p>Active Sessions</p>
                                            </div>
                                            <div class="icon">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small-box bg-success">
                                            <div class="inner">
                                                <h3><?php echo count($login_history); ?></h3>
                                                <p>Total Logins (Last 20)</p>
                                            </div>
                                            <div class="icon">
                                                <i class="fas fa-sign-in-alt"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Terminate All Sessions Button -->
                                <?php if (count($active_sessions) > 1): ?>
                                <div class="mt-3">
                                    <form method="post" action="terminate-session.php" class="d-inline">
                                        <input type="hidden" name="all_sessions" value="1">
                                        <button type="submit" name="terminate_all" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to terminate all other sessions? You will remain logged in only on this device.');">
                                            <i class="fas fa-ban me-2"></i> Terminate All Other Sessions
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No login history found. This is your first login or session data has been cleared.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Image Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="avatarModalLabel">Change Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Select Image</label>
                        <input class="form-control" type="file" id="profile_image" name="profile_image" accept="image/jpeg, image/png, image/gif" required>
                        <div class="form-text">JPG, PNG or GIF. Max size 2MB. Will be resized to 200x200 pixels.</div>
                    </div>
                    <div class="text-center mt-3">
                        <img id="imagePreview" class="img-fluid d-none" alt="Preview">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_avatar" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password match validation
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (newPassword.value != confirmPassword.value) {
            confirmPassword.setCustomValidity("Passwords don't match");
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    if (newPassword && confirmPassword) {
        newPassword.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    }
    
    // Image preview
    const fileInput = document.getElementById('profile_image');
    const imagePreview = document.getElementById('imagePreview');
    
    if (fileInput && imagePreview) {
        fileInput.addEventListener('change', function() {
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.remove('d-none');
                }
                
                reader.readAsDataURL(fileInput.files[0]);
            }
        });
    }
    
    // Avatar selection functionality
    const avatarOptions = document.querySelectorAll('.avatar-option');
    const avatarForm = document.getElementById('avatarSelectionForm');
    const selectedAvatarInput = document.getElementById('selectedAvatarInput');
    
    avatarOptions.forEach(option => {
        option.addEventListener('click', function() {
            const avatarPath = this.dataset.avatar;
            
            // Remove selected class from all options
            avatarOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Update hidden input and submit form
            selectedAvatarInput.value = avatarPath;
            avatarForm.submit();
        });
        
        // Add hover effect
        option.addEventListener('mouseenter', function() {
            if (!this.classList.contains('selected')) {
                this.querySelector('img').style.transform = 'scale(1.05)';
                this.querySelector('img').style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
            }
        });
        
        option.addEventListener('mouseleave', function() {
            if (!this.classList.contains('selected')) {
                this.querySelector('img').style.transform = 'scale(1)';
                this.querySelector('img').style.boxShadow = 'none';
            }
        });
    });
});
</script>

<style>
.avatar-option.selected img {
    border: 3px solid #28a745 !important;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.avatar-option img:hover {
    transition: all 0.3s ease;
}

.avatar-option {
    transition: all 0.3s ease;
}
</style>

<?php require_once 'components/footer.php'; ?>
