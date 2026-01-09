<?php
/**
 * AbroadWorks Management System - Edit Email Header
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

if (!has_permission('timeworks_email_templates')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Get header data
$headerData = null;
try {
    $stmt = $db->prepare("SELECT * FROM email_template_parts WHERE id = ? AND type = 'header'");
    $stmt->execute([$id]);
    $headerData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading header: " . $e->getMessage());
}

if (!$headerData) {
    header('Location: index.php');
    exit;
}

$page_title = "Edit Email Header";
$root_path = '../../../';

include '../../../components/header.php';
include '../../../components/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-edit"></i> Edit Email Header</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Email Headers</a></li>
                        <li class="breadcrumb-item active">Edit Header</li>
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
                    <!-- Header Form Card -->
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-heading"></i> Header Details</h3>
                            <div class="card-tools">
                                <?php if ($headerData['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form id="headerForm">
                            <input type="hidden" name="id" value="<?= $headerData['id'] ?>">
                            <input type="hidden" name="type" value="header">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="headerCode">Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="headerCode" name="code" required pattern="[a-z0-9_]+" value="<?= htmlspecialchars($headerData['code']) ?>">
                                            <small class="form-text text-muted">Lowercase letters, numbers and underscores only</small>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="headerName">Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="headerName" name="name" required value="<?= htmlspecialchars($headerData['name']) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="headerContent">Content (HTML) <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="headerContent" name="content" rows="15" required><?= htmlspecialchars($headerData['content']) ?></textarea>
                                    <small class="form-text text-muted">This HTML will be placed at the beginning of your emails.</small>
                                </div>
                                <div class="form-group">
                                    <label for="headerDescription">Description</label>
                                    <input type="text" class="form-control" id="headerDescription" name="description" value="<?= htmlspecialchars($headerData['description'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="headerActive" name="is_active" <?= $headerData['is_active'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="headerActive">Active</label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                                <button type="button" class="btn btn-info" onclick="previewHeader()">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <button type="submit" class="btn btn-success float-right">
                                    <i class="fas fa-save"></i> Update Header
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-3">
                    <!-- Header Info Card -->
                    <div class="card card-secondary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info"></i> Header Info</h3>
                        </div>
                        <div class="card-body" style="font-size: 0.85rem;">
                            <p><strong>ID:</strong> <?= $headerData['id'] ?></p>
                            <p><strong>Code:</strong> <code><?= htmlspecialchars($headerData['code']) ?></code></p>
                            <p><strong>Created:</strong> <?= $headerData['created_at'] ? date('d.m.Y H:i', strtotime($headerData['created_at'])) : '-' ?></p>
                            <p class="mb-0"><strong>Updated:</strong> <?= $headerData['updated_at'] ? date('d.m.Y H:i', strtotime($headerData['updated_at'])) : '-' ?></p>
                        </div>
                    </div>

                    <!-- Placeholders Info Card -->
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Placeholders</h3>
                        </div>
                        <div class="card-body" style="font-size: 0.85rem;">
                            <h6><strong>System</strong></h6>
                            <ul class="list-unstyled mb-0">
                                <li><code>{{site_name}}</code> - Site name</li>
                                <li><code>{{site_url}}</code> - Site URL</li>
                                <li><code>{{year}}</code> - Current year</li>
                                <li><code>{{date}}</code> - Current date</li>
                                <li><code>{{time}}</code> - Current time</li>
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
                <h5 class="modal-title"><i class="fas fa-eye"></i> Header Preview</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
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
    $('#headerContent').summernote({
        height: 350,
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

    $('#headerForm').on('submit', function(e) {
        e.preventDefault();
        saveHeader();
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

function saveHeader() {
    // Sync Summernote content to textarea before form submission
    $('#headerContent').val($('#headerContent').summernote('code'));

    const formData = new FormData($('#headerForm')[0]);
    formData.append('action', 'save');

    $.ajax({
        url: '../../../api/email-template-parts.php',
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
            showAlert('danger', 'Failed to update header');
        }
    });
}

function previewHeader() {
    const content = $('#headerContent').summernote('code');

    const sampleData = {
        'site_name': 'AbroadWorks IRM',
        'site_url': window.location.origin,
        'year': new Date().getFullYear(),
        'date': new Date().toLocaleDateString(),
        'time': new Date().toLocaleTimeString()
    };

    let previewContent = content || '';
    for (const [key, value] of Object.entries(sampleData)) {
        const regex = new RegExp('\\{\\{' + key + '\\}\\}', 'g');
        previewContent = previewContent.replace(regex, value);
    }

    // Add sample content after header
    previewContent += '<div style="padding: 20px; background: #fff; border: 1px dashed #ccc; margin: 10px;"><p style="color: #999; text-align: center;">[Email content will appear here]</p></div>';

    const iframe = document.getElementById('previewFrame');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    iframeDoc.open();
    iframeDoc.write(previewContent);
    iframeDoc.close();

    $('#previewModal').modal('show');
}
</script>

<?php include '../../../components/footer.php'; ?>
