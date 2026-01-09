<?php
/**
 * TimeWorks Module - Bulk Email Send Page
 *
 * Send bulk emails with tracking functionality
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_email_manage')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['timeworks']);
$is_active = $stmt->fetchColumn();

if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Get templates for dropdown
$templates = [];
try {
    $stmt = $db->query("SELECT id, code, name, subject FROM email_templates WHERE is_active = 1 ORDER BY name ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading templates: " . $e->getMessage());
}

// Get email groups for dropdown
$emailGroups = [];
try {
    $stmt = $db->query("
        SELECT g.id, g.name,
               (SELECT COUNT(*) FROM email_group_members WHERE group_id = g.id) as member_count
        FROM email_groups g
        WHERE g.is_active = 1
        ORDER BY g.name ASC
    ");
    $emailGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading email groups: " . $e->getMessage());
}

// Get recent campaigns
$recentCampaigns = [];
try {
    $stmt = $db->query("
        SELECT c.*, u.name as created_by_name,
               (SELECT COUNT(*) FROM email_sends WHERE campaign_id = c.id AND status = 'sent') as sent_count,
               (SELECT COUNT(*) FROM email_sends WHERE campaign_id = c.id AND status = 'pending') as pending_count
        FROM email_campaigns c
        LEFT JOIN users u ON c.created_by = u.id
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    $recentCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading campaigns: " . $e->getMessage());
}

$page_title = "TimeWorks - Bulk Email";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-mail-bulk text-primary"></i> Bulk Email
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Bulk Email</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Alert Container -->
            <div id="alertContainer"></div>

            <div class="row">
                <!-- Configuration Card -->
                <div class="col-lg-8">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog"></i> Campaign Configuration</h3>
                        </div>
                        <div class="card-body">
                            <form id="campaignForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="campaignName">Campaign Name</label>
                                            <input type="text" class="form-control" id="campaignName" name="name" placeholder="Enter campaign name (optional)">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="templateSelect">Email Template <span class="text-danger">*</span></label>
                                            <select class="form-control" id="templateSelect" name="template_id" required>
                                                <option value="">-- Select Template --</option>
                                                <?php foreach ($templates as $template): ?>
                                                <option value="<?= $template['id'] ?>" data-subject="<?= htmlspecialchars($template['subject']) ?>">
                                                    <?= htmlspecialchars($template['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="recipientFilter">Recipients <span class="text-danger">*</span></label>
                                            <select class="form-control" id="recipientFilter" name="recipient_filter" required>
                                                <optgroup label="User Filters">
                                                    <option value="all">All Users with Email</option>
                                                    <option value="active">Active Users Only</option>
                                                    <option value="inactive">Inactive Users Only</option>
                                                    <option value="without_activity">Users Without Activity</option>
                                                    <option value="with_activity">Users With Activity</option>
                                                </optgroup>
                                                <?php if (!empty($emailGroups)): ?>
                                                <optgroup label="Email Groups">
                                                    <?php foreach ($emailGroups as $group): ?>
                                                    <option value="group:<?= $group['id'] ?>">
                                                        <?= htmlspecialchars($group['name']) ?> (<?= $group['member_count'] ?> members)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">
                                                <span id="recipientCount">0</span> recipients will receive this email
                                                <?php if (!empty($emailGroups)): ?>
                                                <br><a href="email-groups.php" class="small"><i class="fas fa-cog"></i> Manage Groups</a>
                                                <?php else: ?>
                                                <br><a href="email-groups.php" class="small"><i class="fas fa-plus"></i> Create Email Groups</a>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="delaySeconds">Delay Between Emails (seconds)</label>
                                            <input type="number" class="form-control" id="delaySeconds" name="delay_seconds" value="5" min="0" max="60">
                                            <small class="form-text text-muted">0-60 seconds delay between each email</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="emailSubject">Subject <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="emailSubject" name="subject" required placeholder="Email subject line">
                                </div>

                                <div class="form-group mb-3">
                                    <label>Email Body <span class="text-danger">*</span></label>
                                    <div class="mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleEditor">
                                            <i class="fas fa-code"></i> <span id="toggleEditorText">HTML View</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="previewEmail()">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                    </div>
                                    <textarea class="form-control" id="emailBody" name="body" rows="12" required></textarea>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </a>
                                    <div>
                                        <button type="button" class="btn btn-info" onclick="previewEmail()">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                        <button type="submit" class="btn btn-success ms-2">
                                            <i class="fas fa-paper-plane"></i> Create & Send Campaign
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Progress Card (Hidden initially) -->
                    <div class="card card-success card-outline" id="progressCard" style="display: none;">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-paper-plane"></i> Sending Progress</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-warning btn-sm" id="pauseBtn" onclick="pauseCampaign()">
                                    <i class="fas fa-pause"></i> Pause
                                </button>
                                <button type="button" class="btn btn-danger btn-sm ms-1" id="cancelBtn" onclick="cancelCampaign()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" role="progressbar" style="width: 0%;">
                                    <span id="progressText">0%</span>
                                </div>
                            </div>
                            <div class="row text-center mb-3">
                                <div class="col-md-3">
                                    <div class="info-box bg-success mb-0">
                                        <span class="info-box-icon"><i class="fas fa-check"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Sent</span>
                                            <span class="info-box-number" id="sentCount">0</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box bg-danger mb-0">
                                        <span class="info-box-icon"><i class="fas fa-times"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Failed</span>
                                            <span class="info-box-number" id="failedCount">0</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box bg-info mb-0">
                                        <span class="info-box-icon"><i class="fas fa-envelope-open"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Opened</span>
                                            <span class="info-box-number" id="openedCount">0</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="info-box bg-warning mb-0">
                                        <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Pending</span>
                                            <span class="info-box-number" id="pendingCount">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="send-log" style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-sm table-striped" id="sendLogTable">
                                    <thead>
                                        <tr>
                                            <th>Email</th>
                                            <th>Name</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sendLogBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Placeholders Card -->
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Available Placeholders</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body" style="font-size: 0.85rem;">
                            <h6><strong>User</strong></h6>
                            <ul class="list-unstyled mb-2">
                                <li><code>{{name}}</code> - Full name</li>
                                <li><code>{{first_name}}</code> - First name</li>
                                <li><code>{{email}}</code> - Email</li>
                            </ul>
                            <h6><strong>System</strong></h6>
                            <ul class="list-unstyled mb-2">
                                <li><code>{{site_name}}</code> - Site name</li>
                                <li><code>{{company_name}}</code> - Company</li>
                                <li><code>{{login_url}}</code> - Login URL</li>
                            </ul>
                            <h6><strong>Password Reset</strong></h6>
                            <ul class="list-unstyled mb-2">
                                <li><code>{{pwpush_url}}</code> - Password link</li>
                                <li><code>{{expire_days}}</code> - Expiry days</li>
                            </ul>
                            <h6><strong>Announcement</strong></h6>
                            <ul class="list-unstyled mb-0">
                                <li><code>{{announcement_title}}</code></li>
                                <li><code>{{announcement_message}}</code></li>
                                <li><code>{{effective_date}}</code></li>
                                <li><code>{{deadline}}</code></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Recent Campaigns Card -->
                    <div class="card card-secondary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-history"></i> Recent Campaigns</h3>
                            <div class="card-tools">
                                <a href="email-reports.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>Sent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentCampaigns)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No campaigns yet</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recentCampaigns as $campaign): ?>
                                    <tr>
                                        <td>
                                            <a href="email-campaign-detail.php?id=<?= $campaign['id'] ?>">
                                                <?= htmlspecialchars(substr($campaign['name'], 0, 20)) ?>...
                                            </a>
                                        </td>
                                        <td>
                                            <?php
                                            $statusBadge = [
                                                'draft' => 'secondary',
                                                'sending' => 'primary',
                                                'paused' => 'warning',
                                                'completed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge bg-<?= $statusBadge[$campaign['status']] ?? 'secondary' ?>">
                                                <?= ucfirst($campaign['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= $campaign['sent_count'] ?>/<?= $campaign['total_recipients'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Email Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom">
                    <strong>Subject:</strong> <span id="previewSubject"></span>
                </div>
                <iframe id="previewFrame" style="width: 100%; height: 500px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/v4-shims.min.css" rel="stylesheet">
<!-- Summernote JS -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>

<script>
var currentCampaignId = null;
var isSending = false;
var isHtmlView = false;
var summernoteInitialized = false;

$(document).ready(function() {
    // Initialize Summernote
    initSummernote();

    // Load recipient count on filter change
    $('#recipientFilter').on('change', function() {
        loadRecipientCount();
    });

    // Load template on selection
    $('#templateSelect').on('change', function() {
        loadTemplate($(this).val());
    });

    // Toggle HTML view
    $('#toggleEditor').on('click', function() {
        toggleHtmlView();
    });

    // Form submit
    $('#campaignForm').on('submit', function(e) {
        e.preventDefault();
        createAndStartCampaign();
    });

    // Initial load
    loadRecipientCount();
});

function initSummernote() {
    $('#emailBody').summernote({
        height: 300,
        placeholder: 'Write your email content here...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
    summernoteInitialized = true;
    isHtmlView = false;
}

function toggleHtmlView() {
    if (isHtmlView) {
        var content = $('#emailBody').val();
        $('#emailBody').summernote('code', content);
        $('.note-editor').show();
        $('#emailBody').hide();
        isHtmlView = false;
        $('#toggleEditorText').text('HTML View');
        $('#toggleEditor i').removeClass('fa-edit').addClass('fa-code');
    } else {
        var content = $('#emailBody').summernote('code');
        $('#emailBody').summernote('destroy');
        $('#emailBody').val(content).show();
        summernoteInitialized = false;
        isHtmlView = true;
        $('#toggleEditorText').text('Editor View');
        $('#toggleEditor i').removeClass('fa-code').addClass('fa-edit');
    }
}

function loadRecipientCount() {
    var filter = $('#recipientFilter').val();
    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: { action: 'get_recipients', filter: filter, count_only: 1 },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#recipientCount').text(response.count);
            }
        }
    });
}

function loadTemplate(templateId) {
    if (!templateId) {
        $('#emailSubject').val('');
        if (summernoteInitialized) {
            $('#emailBody').summernote('code', '');
        } else {
            $('#emailBody').val('');
        }
        return;
    }

    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: { action: 'get_template', template_id: templateId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#emailSubject').val(response.template.subject);
                if (summernoteInitialized) {
                    $('#emailBody').summernote('code', response.template.body);
                } else {
                    $('#emailBody').val(response.template.body);
                }
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

function previewEmail() {
    var subject = $('#emailSubject').val();
    var body = summernoteInitialized ? $('#emailBody').summernote('code') : $('#emailBody').val();
    var templateId = $('#templateSelect').val();

    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: {
            action: 'preview_email',
            template_id: templateId,
            custom_subject: subject,
            custom_body: body
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#previewSubject').text(response.subject);
                var iframe = document.getElementById('previewFrame');
                var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                iframeDoc.open();
                iframeDoc.write(response.body);
                iframeDoc.close();
                $('#previewModal').modal('show');
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to generate preview');
        }
    });
}

function createAndStartCampaign() {
    var subject = $('#emailSubject').val();
    var body = summernoteInitialized ? $('#emailBody').summernote('code') : $('#emailBody').val();
    var recipientCount = parseInt($('#recipientCount').text());

    if (!subject || !body) {
        showAlert('danger', 'Subject and body are required');
        return;
    }

    if (recipientCount === 0) {
        showAlert('danger', 'No recipients found for selected filter');
        return;
    }

    Swal.fire({
        title: 'Start Campaign?',
        html: `You are about to send emails to <strong>${recipientCount}</strong> recipients.<br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, start sending!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            createCampaign();
        }
    });
}

function createCampaign() {
    var formData = {
        action: 'create_campaign',
        name: $('#campaignName').val(),
        template_id: $('#templateSelect').val(),
        subject: $('#emailSubject').val(),
        body: summernoteInitialized ? $('#emailBody').summernote('code') : $('#emailBody').val(),
        recipient_filter: $('#recipientFilter').val(),
        delay_seconds: $('#delaySeconds').val()
    };

    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                currentCampaignId = response.campaign_id;
                showProgressCard(response.recipient_count);
                startCampaign();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to create campaign');
        }
    });
}

function showProgressCard(totalRecipients) {
    $('#progressCard').show();
    $('#pendingCount').text(totalRecipients);
    $('#sentCount').text(0);
    $('#failedCount').text(0);
    $('#openedCount').text(0);
    $('#progressBar').css('width', '0%');
    $('#progressText').text('0%');
    $('#sendLogBody').empty();
}

function startCampaign() {
    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: { action: 'start_campaign', campaign_id: currentCampaignId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                isSending = true;
                sendNextChunk();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to start campaign');
        }
    });
}

function sendNextChunk() {
    if (!isSending || !currentCampaignId) return;

    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: { action: 'send_chunk', campaign_id: currentCampaignId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.paused) {
                    showAlert('warning', 'Campaign paused');
                    $('#pauseBtn').html('<i class="fas fa-play"></i> Resume').removeClass('btn-warning').addClass('btn-success').attr('onclick', 'resumeCampaign()');
                    return;
                }

                if (response.cancelled) {
                    showAlert('danger', 'Campaign cancelled');
                    return;
                }

                // Update stats
                if (response.stats) {
                    updateStats(response.stats);
                }

                // Add results to log
                if (response.results) {
                    response.results.forEach(function(result) {
                        addToLog(result);
                    });
                }

                if (response.done) {
                    isSending = false;
                    $('#progressBar').removeClass('progress-bar-animated').addClass('bg-success');
                    showAlert('success', 'Campaign completed successfully!');
                    $('#pauseBtn, #cancelBtn').prop('disabled', true);
                } else {
                    // Continue sending
                    setTimeout(sendNextChunk, 500);
                }
            } else {
                showAlert('danger', response.message);
                isSending = false;
            }
        },
        error: function() {
            showAlert('danger', 'Error during sending');
            isSending = false;
        }
    });
}

function updateStats(stats) {
    var total = stats.sent + stats.failed + stats.pending;
    var progress = total > 0 ? Math.round((stats.sent + stats.failed) / total * 100) : 0;

    $('#sentCount').text(stats.sent);
    $('#failedCount').text(stats.failed);
    $('#pendingCount').text(stats.pending);
    $('#openedCount').text(stats.unique_opens);
    $('#progressBar').css('width', progress + '%');
    $('#progressText').text(progress + '%');
}

function addToLog(result) {
    var statusBadge = result.status === 'sent'
        ? '<span class="badge bg-success">Sent</span>'
        : '<span class="badge bg-danger" title="' + (result.error || '') + '">Failed</span>';

    $('#sendLogBody').prepend(`
        <tr>
            <td>${result.email}</td>
            <td>${result.name || '-'}</td>
            <td>${statusBadge}</td>
        </tr>
    `);
}

function pauseCampaign() {
    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: { action: 'pause_campaign', campaign_id: currentCampaignId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                isSending = false;
                showAlert('warning', 'Campaign paused');
                $('#pauseBtn').html('<i class="fas fa-play"></i> Resume').removeClass('btn-warning').addClass('btn-success').attr('onclick', 'resumeCampaign()');
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

function resumeCampaign() {
    $.ajax({
        url: 'api/bulk-email.php',
        type: 'POST',
        data: { action: 'resume_campaign', campaign_id: currentCampaignId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                isSending = true;
                showAlert('info', 'Campaign resumed');
                $('#pauseBtn').html('<i class="fas fa-pause"></i> Pause').removeClass('btn-success').addClass('btn-warning').attr('onclick', 'pauseCampaign()');
                sendNextChunk();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

function cancelCampaign() {
    Swal.fire({
        title: 'Cancel Campaign?',
        text: 'This will stop sending remaining emails. Already sent emails cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, cancel it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/bulk-email.php',
                type: 'POST',
                data: { action: 'cancel_campaign', campaign_id: currentCampaignId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        isSending = false;
                        showAlert('danger', 'Campaign cancelled');
                        $('#progressBar').removeClass('progress-bar-animated').addClass('bg-danger');
                        $('#pauseBtn, #cancelBtn').prop('disabled', true);
                    } else {
                        showAlert('danger', response.message);
                    }
                }
            });
        }
    });
}

function showAlert(type, message) {
    var alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    $('#alertContainer').html(alertHtml);
    setTimeout(function() {
        $('#alertContainer .alert').alert('close');
    }, 5000);
}
</script>

<?php include '../../components/footer.php'; ?>
