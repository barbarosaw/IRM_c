<?php
/**
 * TimeWorks Module - Bulk Deactivate Users
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['timeworks']);
$is_active = $stmt->fetchColumn();
if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Set timezone to EST
date_default_timezone_set('America/New_York');

$page_title = "TimeWorks - Bulk Deactivate";
$root_path = "../../";

// Users to deactivate
$namesToDeactivate = [
    'Adrianne Aguilar',
    'Akshoy Saha',
    "Amir's test",
    'Amlan',
    'Audrey Rebojo',
    'Benita Orinya',
    'Chaim Mordechai',
    'Christian Jade Tan',
    'Christian Mallari',
    'Danice Villareal',
    'Dennis Pepito',
    'Dip Test User_1',
    'Dip kole',
    'Edgar Gutierrez Eng',
    'Florabelle Gamilla',
    'Giovanni Tambahoyot',
    'Gitanjan',
    'Gladys Isabel Umayam',
    'Greg Jaeger',
    'Ian Sadia',
    'Irish Vibal',
    'JELLIE MAR MORENO PATALITA',
    'James Patrick Jubay',
    'Jayvee Cabug',
    'Jenalin Sacro',
    'Jersey Lynn Anne Tualla',
    'Jezriel Loreto',
    'Jhustine Paola Cabael',
    'John Davis',
    'John Doe',
    'John Venedict Rocero',
    'Jorge Preciado',
    'Jose Enriquez',
    'Jose Fernandez',
    'Joyce Nicole Santos',
    'Juan Tesoro',
    'Juan Torres',
    'Juliet Paligan',
    'Kalyan Debnath',
    'Kimberly Antonio',
    'Kleavon Musngi',
    'Kristine Ann Moore',
    'Lanz Acera',
    'Liam Rafael Nobleza',
    'Lillian Martinez',
    'Matthew Madregalejo',
    'Miguel Guevara',
    'Mike Roston',
    'Mikhaella Corpuz',
    'Motty Roston',
    'Mylene Enriquez',
    'Neil Bucsit',
    'Pablo Emilio Aviles Gonzalez',
    'Paloma Eliserio',
    'Paradigm Team',
    'Paul Sial',
    'Paula Febe Araña',
    'Phyl Stone',
    'Radha Ghosh',
    'Regina Matesanz',
    'Rhayne Anne Layson',
    'Rhea Angelica Sumalinog',
    'Rocky Salvaleon',
    'Rohit Katiyar',
    'Ryan Ayag',
    'Ryan Tuazon',
    'Shahid',
    'Shanice Quitalig',
    'Shereen Thomas',
    'Subham',
    'Subhasis Bag',
    'Sujoy',
    'Test',
    'Test User',
    'Test user 1',
    'Test-2',
    'Thomas Earl Benedict Consing',
    'Utpalendu Sarkar',
    'Wency Ducut',
    'Xe Pueblos',
    'Zesty StoDomingo',
    'chandramoli'
];

// Handle bulk deactivate
if (isset($_POST['action']) && $_POST['action'] === 'bulk_deactivate') {
    header('Content-Type: application/json');

    $userIds = $_POST['user_ids'] ?? [];

    if (empty($userIds)) {
        echo json_encode(['success' => false, 'message' => 'No users selected']);
        exit;
    }

    $deactivated = 0;
    foreach ($userIds as $userId) {
        $stmt = $db->prepare("UPDATE twr_users SET status = 'inactive', updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->rowCount() > 0) {
            $deactivated++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "{$deactivated} users deactivated successfully",
        'count' => $deactivated
    ]);
    exit;
}

// Build query to find matching users
$placeholders = implode(',', array_fill(0, count($namesToDeactivate), '?'));
$stmt = $db->prepare("
    SELECT user_id, full_name, email, status, roles, created_at
    FROM twr_users
    WHERE full_name IN ({$placeholders})
    ORDER BY full_name ASC
");
$stmt->execute($namesToDeactivate);
$matchedUsers = $stmt->fetchAll();

// Separate active and already inactive
$activeUsers = [];
$inactiveUsers = [];
foreach ($matchedUsers as $user) {
    if ($user['status'] === 'active') {
        $activeUsers[] = $user;
    } else {
        $inactiveUsers[] = $user;
    }
}

// Find names that didn't match
$matchedNames = array_column($matchedUsers, 'full_name');
$notFoundNames = array_diff($namesToDeactivate, $matchedNames);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-user-times"></i> Bulk Deactivate Users
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Bulk Deactivate</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Summary -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo count($namesToDeactivate); ?></h3>
                            <p>Names in List</p>
                        </div>
                        <div class="icon"><i class="fas fa-list"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo count($matchedUsers); ?></h3>
                            <p>Matched in DB</p>
                        </div>
                        <div class="icon"><i class="fas fa-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo count($activeUsers); ?></h3>
                            <p>Currently Active</p>
                        </div>
                        <div class="icon"><i class="fas fa-user-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo count($notFoundNames); ?></h3>
                            <p>Not Found</p>
                        </div>
                        <div class="icon"><i class="fas fa-question"></i></div>
                    </div>
                </div>
            </div>

            <!-- Active Users to Deactivate -->
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-check"></i> Active Users to Deactivate
                        <span class="badge badge-light ml-2"><?php echo count($activeUsers); ?></span>
                    </h3>
                    <div class="card-tools">
                        <?php if (!empty($activeUsers)): ?>
                        <button type="button" id="btnDeactivateAll" class="btn btn-danger btn-sm">
                            <i class="fas fa-user-times"></i> Deactivate All (<?php echo count($activeUsers); ?>)
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($activeUsers)): ?>
                        <p class="text-muted">No active users to deactivate.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="activeUsersTable" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" id="selectAll" checked>
                                        </th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>User ID</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeUsers as $user): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="user-checkbox" value="<?php echo htmlspecialchars($user['user_id']); ?>" checked>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><code style="font-size: 10px;"><?php echo htmlspecialchars($user['user_id']); ?></code></td>
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($user['roles']); ?></span></td>
                                            <td><span class="badge badge-success">Active</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Already Inactive Users -->
            <?php if (!empty($inactiveUsers)): ?>
            <div class="card card-secondary collapsed-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-slash"></i> Already Inactive
                        <span class="badge badge-light ml-2"><?php echo count($inactiveUsers); ?></span>
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>User ID</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inactiveUsers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><code style="font-size: 10px;"><?php echo htmlspecialchars($user['user_id']); ?></code></td>
                                        <td><span class="badge badge-secondary">Inactive</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Not Found Names -->
            <?php if (!empty($notFoundNames)): ?>
            <div class="card card-danger collapsed-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-question-circle"></i> Names Not Found in Database
                        <span class="badge badge-light ml-2"><?php echo count($notFoundNames); ?></span>
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> These names were not found in the database. They may have different spellings or already been deleted.
                    </div>
                    <ul class="list-group">
                        <?php foreach ($notFoundNames as $name): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Select All checkbox
    $('#selectAll').on('change', function() {
        $('.user-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Individual checkbox change
    $('.user-checkbox').on('change', function() {
        var allChecked = $('.user-checkbox:checked').length === $('.user-checkbox').length;
        $('#selectAll').prop('checked', allChecked);
    });

    // Deactivate All button
    $('#btnDeactivateAll').on('click', function() {
        var selectedIds = [];
        $('.user-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire('Warning', 'No users selected', 'warning');
            return;
        }

        Swal.fire({
            title: 'Confirm Deactivation',
            html: 'Are you sure you want to deactivate <strong>' + selectedIds.length + '</strong> users?<br><br>This will exclude them from syncs and reports.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, Deactivate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'bulk-deactivate.php',
                    method: 'POST',
                    data: {
                        action: 'bulk_deactivate',
                        user_ids: selectedIds
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: response.message,
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'An error occurred', 'error');
                    }
                });
            }
        });
    });

    // Initialize DataTable
    $('#activeUsersTable').DataTable({
        responsive: true,
        pageLength: 100,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: 0 }
        ]
    });
});
</script>

<?php include '../../components/footer.php'; ?>
