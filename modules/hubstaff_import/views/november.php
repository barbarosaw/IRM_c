<?php
/**
 * Hubstaff Import Module - November View
 */

$exportsDir = __DIR__ . '/../exports';
$stateFile = $exportsDir . '/state_november.json';
$projectsFile = $exportsDir . '/projects.json';

// Load state
$state = [];
if (file_exists($stateFile)) {
    $state = json_decode(file_get_contents($stateFile), true) ?: [];
}

// Get exported files
$exportedUsers = [];
$files = glob($exportsDir . '/*_november_2025.csv');
foreach ($files as $file) {
    $filename = basename($file);
    $name = str_replace('_november_2025.csv', '', $filename);
    $name = str_replace('_', ' ', $name);
    $exportedUsers[] = [
        'filename' => $filename,
        'name' => ucwords($name),
        'size' => filesize($file),
        'modified' => filemtime($file),
        'path' => $file
    ];
}

// Sort by name
usort($exportedUsers, function($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

$totalUsers = count($exportedUsers);
$processingUser = $state['current_user'] ?? null;
$isProcessing = $state['processing'] ?? false;
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Hubstaff Import (November)</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Hubstaff Import</a></li>
                        <li class="breadcrumb-item active">November</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <!-- Navigation -->
            <div class="mb-3">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-calendar me-2"></i>December 2025
                </a>
                <a href="november.php" class="btn btn-primary">
                    <i class="fas fa-calendar-alt me-2"></i>November 2025
                </a>
            </div>

            <!-- Control Panel -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">November 2025 Timesheet Export</h3>
                </div>
                <div class="card-body">
                    <p>Export daily timesheet data from Hubstaff for all employees. Each user will have a separate CSV file.</p>

                    <div class="btn-group mb-3">
                        <button type="button" class="btn btn-info" id="btnTest">
                            <i class="fas fa-flask me-2"></i>Test (Absalon III)
                        </button>
                        <button type="button" class="btn btn-primary" id="btnAutomate">
                            <i class="fas fa-cogs me-2"></i>Automate All Users
                        </button>
                        <?php if ($totalUsers > 0): ?>
                        <button type="button" class="btn btn-success" id="btnDownloadAll">
                            <i class="fas fa-file-archive me-2"></i>Download All (ZIP)
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Progress Section -->
                    <div id="progressSection" style="display: <?= $isProcessing ? 'block' : 'none' ?>;">
                        <div class="alert alert-info">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            <span id="progressText">Processing: <?= htmlspecialchars($processingUser ?? '') ?></span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>

                    <!-- Status Messages -->
                    <div id="statusMessage" class="mt-3" style="display: none;"></div>
                </div>
            </div>

            <!-- Exported Users -->
            <?php if ($totalUsers > 0): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h3 class="card-title">Exported Users</h3>
                    <div class="card-tools">
                        <span class="badge bg-primary"><?= $totalUsers ?> users</span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Search -->
                    <div class="mb-3">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search users...">
                    </div>

                    <!-- Users Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>File Size</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exportedUsers as $user): ?>
                                <tr data-name="<?= strtolower($user['name']) ?>">
                                    <td>
                                        <i class="fas fa-user text-muted me-2"></i>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </td>
                                    <td><?= number_format($user['size'] / 1024, 1) ?> KB</td>
                                    <td><?= date('M d, Y H:i', $user['modified']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info btn-view"
                                                data-file="<?= htmlspecialchars($user['filename']) ?>"
                                                title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="exports/<?= htmlspecialchars($user['filename']) ?>"
                                           class="btn btn-sm btn-outline-primary"
                                           download
                                           title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger btn-delete"
                                                data-file="<?= htmlspecialchars($user['filename']) ?>"
                                                data-name="<?= htmlspecialchars($user['name']) ?>"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card mt-4">
                <div class="card-body text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No exported data yet. Click "Test" or "Automate All Users" to start.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Timesheet Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewLoading" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
                <div id="viewContent" class="table-responsive" style="display: none; max-height: 500px; overflow-y: auto;">
                    <table class="table table-sm table-striped" id="viewTable">
                        <thead class="sticky-top bg-white"></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchInput')?.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        const name = row.dataset.name;
        row.style.display = name.includes(query) ? '' : 'none';
    });
});

// Test button
document.getElementById('btnTest').addEventListener('click', function() {
    if (!confirm('Run test export for Absalon III Rabadon?')) return;
    runImport('test');
});

// Automate button
document.getElementById('btnAutomate').addEventListener('click', function() {
    if (!confirm('Start automated export for all users? This may take a while.')) return;
    runImport('automate');
});

// Download All
document.getElementById('btnDownloadAll')?.addEventListener('click', function() {
    window.location.href = 'download_zip_november.php';
});

// Run import
function runImport(mode) {
    const btn = mode === 'test' ? document.getElementById('btnTest') : document.getElementById('btnAutomate');
    const progressSection = document.getElementById('progressSection');
    const progressText = document.getElementById('progressText');
    const statusMessage = document.getElementById('statusMessage');

    btn.disabled = true;
    progressSection.style.display = 'block';
    statusMessage.style.display = 'none';
    progressText.textContent = 'Starting...';

    fetch('import_november.php?mode=' + mode)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.continue) {
                    // More users to process, reload page
                    progressText.textContent = 'Processed: ' + (data.user_name || 'Unknown') + '. Loading next...';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    // All done
                    statusMessage.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + data.message + '</div>';
                    statusMessage.style.display = 'block';
                    progressSection.style.display = 'none';
                    setTimeout(() => location.reload(), 1500);
                }
            } else {
                statusMessage.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + (data.error || 'Unknown error') + '</div>';
                statusMessage.style.display = 'block';
                progressSection.style.display = 'none';
                btn.disabled = false;
            }
        })
        .catch(err => {
            statusMessage.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Connection error: ' + err.message + '</div>';
            statusMessage.style.display = 'block';
            progressSection.style.display = 'none';
            btn.disabled = false;
        });
}

// Delete button
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {
        const file = this.dataset.file;
        const name = this.dataset.name;
        if (!confirm('Delete export for ' + name + '?')) return;

        fetch('delete_export_november.php?file=' + encodeURIComponent(file))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
            });
    });
});

// View button
document.querySelectorAll('.btn-view').forEach(btn => {
    btn.addEventListener('click', function() {
        const file = this.dataset.file;
        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        const loading = document.getElementById('viewLoading');
        const content = document.getElementById('viewContent');
        const table = document.getElementById('viewTable');

        document.querySelector('#viewModal .modal-title').textContent = file.replace('_november_2025.csv', '').replace(/_/g, ' ').toUpperCase();
        loading.style.display = 'block';
        content.style.display = 'none';
        modal.show();

        fetch('view_csv_november.php?file=' + encodeURIComponent(file))
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                content.style.display = 'block';

                if (data.error) {
                    content.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                    return;
                }

                // Build table
                let headerHtml = '<tr>';
                data.headers.forEach(h => {
                    headerHtml += '<th>' + h + '</th>';
                });
                headerHtml += '</tr>';
                table.querySelector('thead').innerHTML = headerHtml;

                let bodyHtml = '';
                data.rows.forEach(row => {
                    bodyHtml += '<tr>';
                    row.forEach(cell => {
                        bodyHtml += '<td>' + (cell || '') + '</td>';
                    });
                    bodyHtml += '</tr>';
                });
                table.querySelector('tbody').innerHTML = bodyHtml;
            })
            .catch(err => {
                loading.style.display = 'none';
                content.innerHTML = '<div class="alert alert-danger">Error loading file: ' + err.message + '</div>';
                content.style.display = 'block';
            });
    });
});

// Auto-continue if processing
<?php if ($isProcessing): ?>
setTimeout(() => {
    fetch('import_november.php?mode=continue')
        .then(response => response.json())
        .then(data => {
            if (data.continue) {
                setTimeout(() => location.reload(), 2000);
            } else {
                location.reload();
            }
        });
}, 1000);
<?php endif; ?>
</script>
