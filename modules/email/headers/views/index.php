<?php
/**
 * AbroadWorks Management System - Email Headers View
 *
 * @author ikinciadam@gmail.com
 */

if (!defined('AW_SYSTEM')) {
    die('Direct access not permitted');
}
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-heading"></i> Email Headers</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>modules/email/">Email Templates</a></li>
                        <li class="breadcrumb-item active">Headers</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Alert Container -->
            <div id="alertContainer"></div>

            <!-- Headers Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-list"></i> Manage Email Headers
                    </h3>
                    <div class="card-tools">
                        <a href="../images/" class="btn btn-info btn-sm me-2">
                            <i class="fas fa-images"></i> Images
                        </a>
                        <a href="add.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> New Header
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <table id="headersTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Code</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($headers as $header): ?>
                            <tr data-id="<?= $header['id'] ?>">
                                <td><code><?= htmlspecialchars($header['code']) ?></code></td>
                                <td><?= htmlspecialchars($header['name']) ?></td>
                                <td><?= htmlspecialchars($header['description'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php if ($header['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-info" title="Preview" onclick="previewHeader(<?= $header['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="edit.php?id=<?= $header['id'] ?>" class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-<?= $header['is_active'] ? 'warning' : 'success' ?>" title="<?= $header['is_active'] ? 'Deactivate' : 'Activate' ?>" onclick="toggleHeader(<?= $header['id'] ?>)">
                                            <i class="fas fa-<?= $header['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger" title="Delete" onclick="deleteHeader(<?= $header['id'] ?>, '<?= htmlspecialchars($header['name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Placeholders Info Card -->
            <div class="card card-outline card-info collapsed-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle"></i> Available Placeholders for Headers
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><strong>System Placeholders</strong></h6>
                            <ul class="list-unstyled">
                                <li><code>{{site_name}}</code> - Site name</li>
                                <li><code>{{site_url}}</code> - Site URL</li>
                                <li><code>{{year}}</code> - Current year</li>
                                <li><code>{{date}}</code> - Current date</li>
                                <li><code>{{time}}</code> - Current time</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><strong>Usage Tips</strong></h6>
                            <ul class="list-unstyled text-muted">
                                <li>Headers appear at the top of emails</li>
                                <li>Include branding, logo, and styles</li>
                                <li>Should open HTML structure</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> Header Preview
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <iframe id="previewFrame" style="width: 100%; height: 400px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Confirm Delete
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the header "<strong id="deleteHeaderName"></strong>"?</p>
                <p class="text-danger mb-0"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="deleteHeaderId">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#headersTable').DataTable({
        "order": [[1, "asc"]],
        "pageLength": 25,
        "language": {
            "emptyTable": "No email headers found"
        }
    });
});

// Show alert message
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    $('#alertContainer').html(alertHtml);

    setTimeout(function() {
        $('#alertContainer .alert').alert('close');
    }, 5000);
}

// Preview header
function previewHeader(id) {
    $.ajax({
        url: '../../../api/email-template-parts.php',
        type: 'GET',
        data: { action: 'get', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showPreview(response.part.content);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load header');
        }
    });
}

// Show preview in iframe
function showPreview(content) {
    // Replace placeholders with sample data
    const sampleData = {
        'site_name': 'AbroadWorks IRM',
        'site_url': window.location.origin,
        'year': new Date().getFullYear(),
        'date': new Date().toLocaleDateString(),
        'time': new Date().toLocaleTimeString()
    };

    let previewContent = content;
    for (const [key, value] of Object.entries(sampleData)) {
        const regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
        previewContent = previewContent.replace(regex, value);
    }

    // Add sample content after header
    previewContent += '<div style="padding: 20px; background: #fff;"><p style="color: #666;">[Email content will appear here]</p></div>';

    const iframe = document.getElementById('previewFrame');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(previewContent);
    iframeDoc.close();

    $('#previewModal').modal('show');
}

// Toggle header active status
function toggleHeader(id) {
    $.ajax({
        url: '../../../api/email-template-parts.php',
        type: 'POST',
        data: { action: 'toggle', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to toggle header status');
        }
    });
}

// Open delete confirmation modal
function deleteHeader(id, name) {
    $('#deleteHeaderId').val(id);
    $('#deleteHeaderName').text(name);
    $('#deleteModal').modal('show');
}

// Confirm and execute delete
function confirmDelete() {
    const id = $('#deleteHeaderId').val();

    $.ajax({
        url: '../../../api/email-template-parts.php',
        type: 'POST',
        data: { action: 'delete', id: id },
        dataType: 'json',
        success: function(response) {
            $('#deleteModal').modal('hide');
            if (response.success) {
                showAlert('success', response.message);
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            $('#deleteModal').modal('hide');
            showAlert('danger', 'Failed to delete header');
        }
    });
}
</script>
