<?php
/**
 * TimeWorks Module - Projects List
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

$page_title = "TimeWorks - Projects";
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
                        <i class="fas fa-project-diagram"></i> TimeWorks Projects
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Projects</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> All Projects</h3>
                    <div class="card-tools">
                        <a href="index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="projectsTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Members</th>
                                    <th>Tasks</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->query("
                                    SELECT *
                                    FROM twr_projects
                                    ORDER BY name ASC
                                ");
                                $projects = $stmt->fetchAll();

                                foreach ($projects as $project):
                                    $statusBadge = $project['status'] === 'active' ? 'success' : 'secondary';
                                ?>
                                    <tr>
                                        <td><?php echo $project['id']; ?></td>
                                        <td>
                                            <a href="project-detail.php?id=<?php echo $project['project_id']; ?>" class="text-primary">
                                                <strong><?php echo htmlspecialchars($project['name']); ?></strong>
                                            </a>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php
                                                $desc = htmlspecialchars($project['description']);
                                                echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $statusBadge; ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo number_format($project['member_count']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php echo number_format($project['task_count']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('M j, Y', strtotime($project['updated_at'])); ?>
                                            </small>
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
    $('#projectsTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[1, 'asc']],
        columnDefs: [
            { targets: [0], width: '50px' },
            { targets: [2], width: '200px' }
        ],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search projects..."
        }
    });
});
</script>

<?php include '../../components/footer.php'; ?>
