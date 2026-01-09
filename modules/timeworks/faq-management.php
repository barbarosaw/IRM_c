<?php
/**
 * TimeWorks Module - FAQ Management
 *
 * Admin page to manage FAQ entries
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check - using timeworks_users_view for now
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

$page_title = "TimeWorks - FAQ Management";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

include '../../components/header.php';
include '../../components/sidebar.php';

// Fetch all FAQ entries
$stmt = $db->query("SELECT * FROM twr_faq ORDER BY sort_order ASC, id ASC");
$faqs = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-question-circle"></i> FAQ Management
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">FAQ Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list"></i> FAQ Entries</h3>
                            <div class="card-tools">
                                <a href="faq.php" target="_blank" class="btn btn-sm btn-info me-2">
                                    <i class="fas fa-external-link-alt"></i> View Public FAQ
                                </a>
                                <button type="button" class="btn btn-sm btn-success" onclick="openAddModal()">
                                    <i class="fas fa-plus"></i> Add New FAQ
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Order</th>
                                        <th>Question</th>
                                        <th style="width: 100px;">Status</th>
                                        <th style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="faq-list">
                                    <?php if (empty($faqs)): ?>
                                        <tr id="no-faq-row">
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                                No FAQ entries yet. Click "Add New FAQ" to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($faqs as $faq): ?>
                                            <tr id="faq-row-<?php echo $faq['id']; ?>">
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $faq['sort_order']; ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($faq['question']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($faq['answer'], 0, 100)) . (strlen($faq['answer']) > 100 ? '...' : ''); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($faq['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="editFaq(<?php echo $faq['id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteFaq(<?php echo $faq['id']; ?>)" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <a href="faq.php#faq-<?php echo $faq['id']; ?>" target="_blank" class="btn btn-sm btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Information</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Public FAQ URL:</strong></p>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control form-control-sm" id="faq-url" value="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/modules/timeworks/faq.php'; ?>" readonly>
                                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyUrl()">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <hr>
                            <p class="mb-2"><strong>Tips:</strong></p>
                            <ul class="small text-muted ps-3">
                                <li>Each FAQ has a unique link for direct access</li>
                                <li>Use "Order" to control display sequence</li>
                                <li>Inactive FAQs won't appear on the public page</li>
                                <li>The FAQ page is linked from the password reset page</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit FAQ Modal -->
<div class="modal fade" id="faqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New FAQ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="faq-form">
                <div class="modal-body">
                    <input type="hidden" id="faq-id" name="id" value="">

                    <div class="mb-3">
                        <label for="faq-question" class="form-label">Question <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="faq-question" name="question" required placeholder="Enter the question...">
                    </div>

                    <div class="mb-3">
                        <label for="faq-answer" class="form-label">Answer <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="faq-answer" name="answer" rows="6" required placeholder="Enter the answer..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="faq-order" class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="faq-order" name="sort_order" value="0" min="0">
                                <small class="text-muted">Lower numbers appear first</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="faq-active" name="is_active" checked>
                                    <label class="form-check-label" for="faq-active">Active (visible on public page)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-save">
                        <i class="fas fa-save"></i> Save FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Summernote Lite CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<style>
.note-editor.note-frame { border: 1px solid #ced4da; border-radius: 0.375rem; }
.note-editor .note-toolbar { background: #f8f9fa; border-bottom: 1px solid #dee2e6; border-radius: 0.375rem 0.375rem 0 0; }
.note-editor .note-editing-area { background: #fff; }
.note-editor .note-statusbar { background: #f8f9fa; border-top: 1px solid #dee2e6; }
</style>

<?php include '../../components/footer.php'; ?>

<script>
const faqModal = new bootstrap.Modal(document.getElementById('faqModal'));
let editorInitialized = false;

// Initialize Summernote editor
function initEditor() {
    if (editorInitialized) {
        $('#faq-answer').summernote('destroy');
    }
    $('#faq-answer').summernote({
        height: 200,
        placeholder: 'Enter the answer...',
        toolbar: [
            ['style', ['bold', 'italic', 'underline']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link']],
            ['view', ['codeview']]
        ]
    });
    editorInitialized = true;
}

// Modal events
document.getElementById('faqModal').addEventListener('shown.bs.modal', function() {
    initEditor();
});

document.getElementById('faqModal').addEventListener('hidden.bs.modal', function() {
    if (editorInitialized) {
        $('#faq-answer').summernote('destroy');
        editorInitialized = false;
    }
});

// Copy URL to clipboard
function copyUrl() {
    const urlInput = document.getElementById('faq-url');
    urlInput.select();
    document.execCommand('copy');

    Swal.fire({
        icon: 'success',
        title: 'Copied!',
        text: 'FAQ URL copied to clipboard',
        timer: 1500,
        showConfirmButton: false
    });
}

// Open add modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New FAQ';
    document.getElementById('faq-form').reset();
    document.getElementById('faq-id').value = '';
    document.getElementById('faq-active').checked = true;
    faqModal.show();
    // Clear editor content after modal is shown
    setTimeout(function() {
        if (editorInitialized) {
            $('#faq-answer').summernote('code', '');
        }
    }, 100);
}

// Edit FAQ
function editFaq(id) {
    // Fetch FAQ data
    fetch('api/faq.php?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalTitle').textContent = 'Edit FAQ';
                document.getElementById('faq-id').value = data.faq.id;
                document.getElementById('faq-question').value = data.faq.question;
                document.getElementById('faq-answer').value = data.faq.answer;
                document.getElementById('faq-order').value = data.faq.sort_order;
                document.getElementById('faq-active').checked = data.faq.is_active == 1;
                faqModal.show();
                // Set editor content after modal is shown
                setTimeout(function() {
                    if (editorInitialized) {
                        $('#faq-answer').summernote('code', data.faq.answer);
                    }
                }, 100);
            } else {
                Swal.fire('Error', data.message || 'Failed to load FAQ', 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Failed to load FAQ', 'error');
        });
}

// Delete FAQ
function deleteFaq(id) {
    Swal.fire({
        title: 'Delete FAQ?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/faq.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('faq-row-' + id).remove();
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'FAQ has been deleted.',
                        timer: 1500,
                        showConfirmButton: false
                    });

                    // Check if table is empty
                    if (document.querySelectorAll('#faq-list tr').length === 0) {
                        location.reload();
                    }
                } else {
                    Swal.fire('Error', data.message || 'Failed to delete FAQ', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Failed to delete FAQ', 'error');
            });
        }
    });
}

// Save FAQ
document.getElementById('faq-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const btn = document.getElementById('btn-save');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    // Get answer from Summernote editor
    const answerContent = editorInitialized ? $('#faq-answer').summernote('code') : document.getElementById('faq-answer').value;

    const formData = {
        action: document.getElementById('faq-id').value ? 'update' : 'create',
        id: document.getElementById('faq-id').value,
        question: document.getElementById('faq-question').value,
        answer: answerContent,
        sort_order: document.getElementById('faq-order').value,
        is_active: document.getElementById('faq-active').checked ? 1 : 0
    };

    fetch('api/faq.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            faqModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message || 'FAQ saved successfully.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', data.message || 'Failed to save FAQ', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Failed to save FAQ', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>
