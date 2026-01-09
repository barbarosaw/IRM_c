<?php
/**
 * AbroadWorks Management System - Email Templates View
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
                    <h1><i class="fas fa-envelope-open-text"></i> Email Templates</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item active">Email Templates</li>
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

            <!-- Templates Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-alt"></i> Manage Templates
                    </h3>
                    <div class="card-tools">
                        <a href="images/" class="btn btn-info btn-sm me-2">
                            <i class="fas fa-images"></i> Images
                        </a>
                        <a href="template-add.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> New Template
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <table id="templatesTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Code</th>
                                <th>Name</th>
                                <th>Subject</th>
                                <th style="width: 100px;">Status</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                            <tr data-id="<?= $template['id'] ?>">
                                <td><code><?= htmlspecialchars($template['code']) ?></code></td>
                                <td><?= htmlspecialchars($template['name']) ?></td>
                                <td><?= htmlspecialchars($template['subject']) ?></td>
                                <td class="text-center">
                                    <?php if ($template['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-info" title="Preview" onclick="previewTemplate(<?= $template['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="template-edit.php?id=<?= $template['id'] ?>" class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-<?= $template['is_active'] ? 'warning' : 'success' ?>" title="<?= $template['is_active'] ? 'Deactivate' : 'Activate' ?>" onclick="toggleTemplate(<?= $template['id'] ?>)">
                                            <i class="fas fa-<?= $template['is_active'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger" title="Delete" onclick="deleteTemplate(<?= $template['id'] ?>, '<?= htmlspecialchars($template['name'], ENT_QUOTES) ?>')">
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
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle"></i> Available Placeholders
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6><strong>User Placeholders</strong></h6>
                            <ul class="list-unstyled">
                                <li><code>{{name}}</code> - User's full name</li>
                                <li><code>{{first_name}}</code> - First name</li>
                                <li><code>{{last_name}}</code> - Last name</li>
                                <li><code>{{email}}</code> - Email address</li>
                                <li><code>{{username}}</code> - Username</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6><strong>System Placeholders</strong></h6>
                            <ul class="list-unstyled">
                                <li><code>{{site_name}}</code> - Site name</li>
                                <li><code>{{site_url}}</code> - Site URL</li>
                                <li><code>{{date}}</code> - Current date</li>
                                <li><code>{{time}}</code> - Current time</li>
                                <li><code>{{year}}</code> - Current year</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6><strong>Activity Placeholders</strong></h6>
                            <ul class="list-unstyled">
                                <li><code>{{last_login}}</code> - Last login date</li>
                                <li><code>{{last_activity}}</code> - Last activity</li>
                                <li><code>{{activity_days}}</code> - Days since activity</li>
                                <li><code>{{login_url}}</code> - Login page URL</li>
                            </ul>
                            <h6 class="mt-3"><strong>Password Reset Placeholders</strong></h6>
                            <ul class="list-unstyled">
                                <li><code>{{pwpush_url}}</code> - Secure password link</li>
                                <li><code>{{expire_days}}</code> - Days until link expires</li>
                                <li><code>{{expire_views}}</code> - Views until link expires</li>
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
                    <i class="fas fa-eye"></i> Email Preview
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <strong>Subject:</strong> <span id="previewSubject"></span>
                </div>
                <iframe id="previewFrame" style="width: 100%; height: 500px; border: none;"></iframe>
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
                <p>Are you sure you want to delete the template "<strong id="deleteTemplateName"></strong>"?</p>
                <p class="text-danger mb-0"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="deleteTemplateId">
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
    $('#templatesTable').DataTable({
        "order": [[1, "asc"]],
        "pageLength": 25,
        "language": {
            "emptyTable": "No email templates found"
        }
    });
});

// Show alert message
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    $('#alertContainer').html(alertHtml);

    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        $('#alertContainer .alert').alert('close');
    }, 5000);
}

// Preview template from table
function previewTemplate(id) {
    $.ajax({
        url: '../../api/email-templates.php',
        type: 'GET',
        data: { action: 'get', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showPreview(response.template.subject, response.template.body);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to load template');
        }
    });
}

// Show preview in iframe
function showPreview(subject, body) {
    // Replace placeholders with sample data
    const sampleData = {
        'name': 'John Doe',
        'first_name': 'John',
        'last_name': 'Doe',
        'email': 'john.doe@example.com',
        'username': 'johndoe',
        'site_name': 'AbroadWorks IRM',
        'site_url': window.location.origin,
        'date': new Date().toLocaleDateString(),
        'time': new Date().toLocaleTimeString(),
        'year': new Date().getFullYear(),
        'last_login': '2025-01-01 09:00:00',
        'last_activity': '2025-01-01',
        'activity_days': '30',
        'login_url': window.location.origin + '/login.php',
        'pwpush_url': 'https://pwpush.com/p/sample-token-xyz',
        'expire_days': '7',
        'expire_views': '5'
    };

    let previewSubject = subject;
    let previewBody = body;

    for (const [key, value] of Object.entries(sampleData)) {
        const regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
        previewSubject = previewSubject.replace(regex, value);
        previewBody = previewBody.replace(regex, value);
    }

    $('#previewSubject').text(previewSubject);

    const iframe = document.getElementById('previewFrame');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(previewBody);
    iframeDoc.close();

    $('#previewModal').modal('show');
}

// Toggle template active status
function toggleTemplate(id) {
    $.ajax({
        url: '../../api/email-templates.php',
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
            showAlert('danger', 'Failed to toggle template status');
        }
    });
}

// Open delete confirmation modal
function deleteTemplate(id, name) {
    $('#deleteTemplateId').val(id);
    $('#deleteTemplateName').text(name);
    $('#deleteModal').modal('show');
}

// Confirm and execute delete
function confirmDelete() {
    const id = $('#deleteTemplateId').val();

    $.ajax({
        url: '../../api/email-templates.php',
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
            showAlert('danger', 'Failed to delete template');
        }
    });
}
</script>
