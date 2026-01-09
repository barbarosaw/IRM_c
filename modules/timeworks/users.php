<?php
/**
 * TimeWorks Module - Users List
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check
if (!has_permission('timeworks_users_view')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['timeworks']);
$is_active = $stmt->fetchColumn();
if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

$page_title = "TimeWorks - Users";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-users"></i> TimeWorks Users
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Users</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> All Users</h3>
                    <div class="card-tools">
                        <?php if (has_permission('timeworks_email_manage')): ?>
                        <a href="bulk-email.php" class="btn btn-sm btn-success me-2">
                            <i class="fas fa-mail-bulk"></i> Bulk Email
                        </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Timezone</th>
                                    <th>Last Login</th>
                                    <th>Projects</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->query("
                                    SELECT
                                        u.*,
                                        COUNT(DISTINCT up.project_id) as project_count
                                    FROM twr_users u
                                    LEFT JOIN twr_user_projects up ON u.user_id = up.user_id
                                    GROUP BY u.id
                                    ORDER BY u.full_name ASC
                                ");
                                $users = $stmt->fetchAll();

                                foreach ($users as $user):
                                    $statusBadge = $user['status'] === 'active' ? 'success' : 'secondary';
                                ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <a href="user-detail.php?id=<?php echo $user['user_id']; ?>" class="text-primary">
                                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?php echo htmlspecialchars($user['roles']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $statusBadge; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['timezone']); ?></small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php
                                                if ($user['last_login_local']) {
                                                    echo date('M j, Y H:i', strtotime($user['last_login_local']));
                                                } else {
                                                    echo '<span class="text-muted">Never</span>';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo number_format($user['project_count']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (has_permission('timeworks_users_manage')): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info btn-reset-password"
                                                        data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                        data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-<?php echo $user['status'] === 'active' ? 'warning' : 'success'; ?> btn-toggle-status"
                                                        data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>"
                                                        data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                        data-current-status="<?php echo $user['status']; ?>"
                                                        title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $user['status'] === 'active' ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#usersTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[1, 'asc']],
        columnDefs: [
            { targets: [0], width: '50px' },
            { targets: [8], width: '100px', orderable: false }
        ],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users..."
        }
    });

    // Reset password
    $(document).on('click', '.btn-reset-password', function() {
        var $btn = $(this);
        var userId = $btn.data('user-id');
        var userName = $btn.data('user-name');
        var userEmail = $btn.data('user-email');

        Swal.fire({
            title: 'Reset Password',
            html: 'Are you sure you want to reset password for:<br><strong>' + userName + '</strong><br><small class="text-muted">' + userEmail + '</small><br><br>A new password will be generated and sent to the user via email.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#17a2b8',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-key"></i> Reset Password',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Processing...',
                    html: 'Generating password and sending email...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'api/reset-password.php',
                    method: 'POST',
                    data: {
                        user_id: userId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Password Reset!',
                                html: response.message,
                                confirmButtonColor: '#28a745'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred: ' + error
                        });
                    }
                });
            }
        });
    });

    // Toggle user status
    $(document).on('click', '.btn-toggle-status', function() {
        var $btn = $(this);
        var userId = $btn.data('user-id');
        var userName = $btn.data('user-name');
        var currentStatus = $btn.data('current-status');
        var newStatus = currentStatus === 'active' ? 'inactive' : 'active';

        Swal.fire({
            title: 'Confirm Status Change',
            html: 'Are you sure you want to <strong>' + (newStatus === 'active' ? 'activate' : 'deactivate') + '</strong> user:<br><strong>' + userName + '</strong>?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: newStatus === 'active' ? '#28a745' : '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, ' + (newStatus === 'active' ? 'activate' : 'deactivate') + ' it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Disable button during request
                $btn.prop('disabled', true);

                $.ajax({
                    url: 'api/toggle_user_status.php',
                    method: 'POST',
                    data: {
                        user_id: userId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred: ' + error
                        });
                        $btn.prop('disabled', false);
                    }
                });
            }
        });
    });
});
</script>

<?php include '../../components/footer.php'; ?>
