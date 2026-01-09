<?php
/**
 * AbroadWorks Management System - Add Email Template
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!has_permission('timeworks_email_templates')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Get available headers and footers
$headers = [];
$footers = [];
try {
    $stmt = $db->prepare("SELECT id, code, name FROM email_template_parts WHERE type = 'header' AND is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $headers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT id, code, name FROM email_template_parts WHERE type = 'footer' AND is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $footers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading headers/footers: " . $e->getMessage());
}

$page_title = "Add Email Template";
$root_path = '../../';

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-plus-circle"></i> Add Email Template</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Email Templates</a></li>
                        <li class="breadcrumb-item active">Add Template</li>
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

            <div class="row">
                <div class="col-lg-9">
                    <!-- Template Form Card -->
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-file-alt"></i> Template Details</h3>
                        </div>
                        <form id="templateForm">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="templateCode">Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="templateCode" name="code" required pattern="[a-z0-9_]+" placeholder="e.g., welcome_email">
                                            <small class="form-text text-muted">Lowercase letters, numbers and underscores only</small>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="templateName">Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="templateName" name="name" required placeholder="Template display name">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="templateSubject">Subject <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="templateSubject" name="subject" required placeholder="Email subject line (placeholders allowed)">
                                </div>
                                <div class="form-group">
                                    <label for="templateBody">Body (HTML) <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="templateBody" name="body" rows="15" required></textarea>
                                </div>
                                <!-- Header/Footer Selection -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="templateHeader"><i class="fas fa-heading text-primary"></i> Email Header</label>
                                            <select class="form-control" id="templateHeader" name="header_id">
                                                <option value="">-- No Header --</option>
                                                <?php foreach ($headers as $header): ?>
                                                <option value="<?= $header['id'] ?>"><?= htmlspecialchars($header['name']) ?> (<?= $header['code'] ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Select a header to prepend to this template</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="templateFooter"><i class="fas fa-shoe-prints text-primary"></i> Email Footer</label>
                                            <select class="form-control" id="templateFooter" name="footer_id">
                                                <option value="">-- No Footer --</option>
                                                <?php foreach ($footers as $footer): ?>
                                                <option value="<?= $footer['id'] ?>"><?= htmlspecialchars($footer['name']) ?> (<?= $footer['code'] ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Select a footer to append to this template</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="templatePlaceholders">Placeholders (for documentation)</label>
                                            <input type="text" class="form-control" id="templatePlaceholders" name="placeholders" placeholder="e.g., name, email, login_url">
                                            <small class="form-text text-muted">Comma-separated list of placeholders used</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="templateDescription">Description</label>
                                            <input type="text" class="form-control" id="templateDescription" name="description" placeholder="Brief description of when this template is used">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="templateActive" name="is_active" checked>
                                        <label class="custom-control-label" for="templateActive">Active</label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                                <button type="button" class="btn btn-info" onclick="previewTemplate()">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <button type="submit" class="btn btn-success float-right">
                                    <i class="fas fa-save"></i> Save Template
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-3">
                    <!-- Placeholders Info Card -->
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Placeholders</h3>
                        </div>
                        <div class="card-body" style="font-size: 0.85rem;">
                            <h6><strong>User</strong></h6>
                            <ul class="list-unstyled mb-3">
                                <li><code>{{name}}</code> - Full name</li>
                                <li><code>{{first_name}}</code> - First name</li>
                                <li><code>{{last_name}}</code> - Last name</li>
                                <li><code>{{email}}</code> - Email</li>
                                <li><code>{{username}}</code> - Username</li>
                            </ul>
                            <h6><strong>System</strong></h6>
                            <ul class="list-unstyled mb-3">
                                <li><code>{{site_name}}</code> - Site name</li>
                                <li><code>{{site_url}}</code> - Site URL</li>
                                <li><code>{{date}}</code> - Current date</li>
                                <li><code>{{time}}</code> - Current time</li>
                                <li><code>{{year}}</code> - Current year</li>
                            </ul>
                            <h6><strong>Activity</strong></h6>
                            <ul class="list-unstyled mb-3">
                                <li><code>{{last_login}}</code> - Last login</li>
                                <li><code>{{activity_days}}</code> - Days inactive</li>
                                <li><code>{{login_url}}</code> - Login URL</li>
                            </ul>
                            <h6><strong>Password Reset</strong></h6>
                            <ul class="list-unstyled">
                                <li><code>{{pwpush_url}}</code> - Secure link</li>
                                <li><code>{{expire_days}}</code> - Expiry days</li>
                                <li><code>{{expire_views}}</code> - Expiry views</li>
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
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Email Preview</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
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

<!-- Summernote Lite -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<style>
.note-editor.note-frame { border: 1px solid #ced4da; border-radius: 0.375rem; }
.note-editor .note-toolbar { background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 0.375rem 0.375rem 0 0; }
.note-editor .note-editing-area { background: #fff; }
.note-editor .note-statusbar { background: #f8f9fa; border-top: 1px solid #dee2e6; }
</style>
<script>
$(document).ready(function() {
    $('#templateBody').summernote({
        height: 350,
        placeholder: 'Write your email content here...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'hr']],
            ['view', ['fullscreen', 'codeview']]
        ],
        dialogsInBody: true
    });

    $('#templateForm').on('submit', function(e) {
        e.preventDefault();
        saveTemplate();
    });
});

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

function saveTemplate() {
    const formData = new FormData($('#templateForm')[0]);
    formData.append('action', 'save');

    $.ajax({
        url: '../../api/email-templates.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to save template');
        }
    });
}

function previewTemplate() {
    const subject = $('#templateSubject').val();
    const body = $('#templateBody').summernote('code');
    const headerId = $('#templateHeader').val();
    const footerId = $('#templateFooter').val();

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

    // Replace placeholders in subject
    let previewSubject = subject || '';
    for (const [key, value] of Object.entries(sampleData)) {
        const regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
        previewSubject = previewSubject.replace(regex, value);
    }
    $('#previewSubject').text(previewSubject);

    // Replace placeholders in body first
    let previewBody = body || '';
    for (const [key, value] of Object.entries(sampleData)) {
        const regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
        previewBody = previewBody.replace(regex, value);
    }

    // If header or footer is selected, get composed preview from API
    if (headerId || footerId) {
        $.ajax({
            url: '../../api/email-template-parts.php',
            type: 'GET',
            data: {
                action: 'preview',
                header_id: headerId,
                footer_id: footerId,
                body: previewBody
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showPreviewInIframe(response.html);
                } else {
                    showPreviewInIframe(previewBody);
                }
            },
            error: function() {
                showPreviewInIframe(previewBody);
            }
        });
    } else {
        showPreviewInIframe(previewBody);
    }

    $('#previewModal').modal('show');
}

function showPreviewInIframe(html) {
    let finalHtml = html;

    // Only wrap if not already a complete HTML document (API returns wrapped HTML)
    if (!html.trim().toLowerCase().startsWith('<!doctype')) {
        // Wrap in 80% width container (same as actual email sending)
        finalHtml = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" width="80%" cellspacing="0" cellpadding="0" border="0" style="max-width: 800px; background-color: #ffffff;">
                    <tr>
                        <td style="padding: 0;">
                            ${html}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`;
    }

    const iframe = document.getElementById('previewFrame');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(finalHtml);
    iframeDoc.close();
}
</script>

<?php include '../../components/footer.php'; ?>
