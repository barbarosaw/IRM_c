<?php
/**
 * TimeWorks Module - Project Detail
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check
if (!has_permission('timeworks_projects_view')) {
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

// Set timezone to EST
date_default_timezone_set('America/New_York');

$page_title = "TimeWorks - Project Detail";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Get project ID from URL
$projectId = $_GET['id'] ?? null;

if (!$projectId) {
    header('Location: projects.php');
    exit;
}

// Get project info
$stmt = $db->prepare("SELECT * FROM twr_projects WHERE project_id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: projects.php');
    exit;
}

// Get project members with their details
$stmt = $db->prepare("
    SELECT u.*, up.assigned_at
    FROM twr_users u
    INNER JOIN twr_user_projects up ON u.user_id = up.user_id
    WHERE up.project_id = ?
    ORDER BY u.full_name ASC
");
$stmt->execute([$projectId]);
$projectMembers = $stmt->fetchAll();

// Calculate member statistics
$activeMembers = 0;
$inactiveMembers = 0;
foreach ($projectMembers as $member) {
    if ($member['status'] === 'active') {
        $activeMembers++;
    } else {
        $inactiveMembers++;
    }
}

// Get role distribution
$roleDistribution = [];
foreach ($projectMembers as $member) {
    $role = $member['roles'] ?? 'Unknown';
    if (!isset($roleDistribution[$role])) {
        $roleDistribution[$role] = 0;
    }
    $roleDistribution[$role]++;
}

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($project['name']); ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                        <li class="breadcrumb-item active">Project Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Project Info Card -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> Project Information</h3>
                    <div class="card-tools">
                        <a href="projects.php" class="btn btn-sm btn-light">
                            <i class="fas fa-arrow-left"></i> Back to Projects
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>Project Name:</th>
                                    <td><strong><?php echo htmlspecialchars($project['name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Description:</th>
                                    <td><?php echo htmlspecialchars($project['description']); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge badge-<?php echo $project['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>Member Count:</th>
                                    <td><strong><?php echo number_format($project['member_count']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Task Count:</th>
                                    <td><strong><?php echo number_format($project['task_count']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td><?php echo date('M j, Y', strtotime($project['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Updated:</th>
                                    <td><?php echo date('M j, Y H:i', strtotime($project['updated_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo count($projectMembers); ?></h3>
                            <p>Total Members</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo $activeMembers; ?></h3>
                            <p>Active Members</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo $inactiveMembers; ?></h3>
                            <p>Inactive Members</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo number_format($project['task_count']); ?></h3>
                            <p>Total Tasks</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Project Members -->
                <div class="col-md-8">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> Project Members</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projectMembers)): ?>
                                <p class="text-muted">No members assigned to this project.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="membersTable" class="table table-bordered table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Assigned</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($projectMembers as $member): ?>
                                                <tr>
                                                    <td>
                                                        <a href="user-detail.php?id=<?php echo $member['user_id']; ?>">
                                                            <strong><?php echo htmlspecialchars($member['full_name']); ?></strong>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                    <td>
                                                        <span class="badge badge-primary">
                                                            <?php echo htmlspecialchars($member['roles']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $member['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($member['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('M j, Y', strtotime($member['assigned_at'])); ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Role Distribution -->
                <div class="col-md-4">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-tag"></i> Members by Role</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($roleDistribution)): ?>
                                <p class="text-muted">No role data available.</p>
                            <?php else: ?>
                                <table class="table table-sm">
                                    <?php foreach ($roleDistribution as $role => $count):
                                        $percentage = (count($projectMembers) > 0) ? ($count / count($projectMembers)) * 100 : 0;
                                    ?>
                                        <tr>
                                            <th><?php echo htmlspecialchars($role); ?></th>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php echo $count; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px; position: relative;">
                                                    <div class="progress-bar bg-info" role="progressbar"
                                                         style="width: <?php echo $percentage; ?>%"
                                                         aria-valuenow="<?php echo $percentage; ?>"
                                                         aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                    <span style="position: absolute; width: 100%; text-align: center; line-height: 20px; color: #000; font-weight: 600;">
                                                        <?php echo number_format($percentage, 1); ?>%
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#membersTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[0, 'asc']],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search members..."
        }
    });
});
</script>

<?php include '../../components/footer.php'; ?>
