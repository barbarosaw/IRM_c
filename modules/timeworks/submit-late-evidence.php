<?php
/**
 * TimeWorks Module - Submit Late Evidence
 *
 * Public page for users to submit evidence for late arrivals.
 * Accessed via unique token sent in email notification.
 *
 * @author ikinciadam@gmail.com
 */

// This page is semi-public - accessed via token
define('AW_SYSTEM', true);
require_once '../../includes/init.php';

// Set timezone
date_default_timezone_set('America/New_York');

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h2 style="color: #dc3545;">Invalid Link</h2>
        <p>This link is invalid or has expired. Please use the link from your email notification.</p>
    </div>');
}

// Validate token and get record
$stmt = $db->prepare("
    SELECT lr.*, u.full_name, u.email
    FROM twr_late_records lr
    LEFT JOIN twr_users u ON lr.user_id = u.user_id
    WHERE lr.evidence_token = ?
");
$stmt->execute([$token]);
$record = $stmt->fetch();

if (!$record) {
    die('<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h2 style="color: #dc3545;">Invalid Token</h2>
        <p>This evidence submission link is invalid. Please check your email for the correct link.</p>
    </div>');
}

// Check if deadline has passed
$deadlinePassed = $record['evidence_deadline'] && strtotime($record['evidence_deadline']) < time();

// Check if evidence already submitted
$alreadySubmitted = !empty($record['evidence_file']) || !empty($record['evidence_notes']);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$deadlinePassed && !$alreadySubmitted) {
    try {
        $noticeType = $_POST['notice_type'] ?? 'none';
        $explanation = trim($_POST['explanation'] ?? '');

        // Validate notice type
        $validNoticeTypes = ['email', 'chat', 'sms', 'phone', 'prior_approval'];
        if (!in_array($noticeType, $validNoticeTypes)) {
            throw new Exception('Please select how you notified about being late.');
        }

        // Handle file upload
        $uploadedFile = null;
        if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['evidence_file'];

            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('File size must be less than 5MB.');
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Only images (JPG, PNG, GIF, WebP) and PDF files are allowed.');
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'evidence_' . $record['id'] . '_' . time() . '.' . strtolower($extension);
            $uploadPath = dirname(__DIR__, 2) . '/assets/uploads/late_evidence/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to upload file. Please try again.');
            }

            $uploadedFile = $filename;
        }

        // Update record
        $stmt = $db->prepare("
            UPDATE twr_late_records
            SET notice_type = ?,
                evidence_file = ?,
                evidence_notes = ?,
                evidence_uploaded_at = NOW(),
                status = 'pending',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$noticeType, $uploadedFile, $explanation, $record['id']]);

        // Refresh record
        $stmt = $db->prepare("
            SELECT lr.*, u.full_name, u.email
            FROM twr_late_records lr
            LEFT JOIN twr_users u ON lr.user_id = u.user_id
            WHERE lr.id = ?
        ");
        $stmt->execute([$record['id']]);
        $record = $stmt->fetch();
        $alreadySubmitted = true;

        $message = 'Your evidence has been submitted successfully. HR will review and notify you of the decision.';
        $messageType = 'success';

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Get site name
$siteName = 'AbroadWorks IRM';
$stmt = $db->query("SELECT value FROM settings WHERE `key` = 'site_name'");
$setting = $stmt->fetchColumn();
if ($setting) {
    $siteName = $setting;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Late Evidence - <?php echo htmlspecialchars($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .evidence-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
        }
        .evidence-header {
            background: #343a40;
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .evidence-body {
            padding: 30px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 20px;
        }
        .deadline-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .deadline-expired {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .already-submitted {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .file-upload-zone {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload-zone:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }
        .file-upload-zone.dragover {
            border-color: #28a745;
            background: #e8f5e9;
        }
        .notice-type-options .form-check {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .notice-type-options .form-check:hover {
            background: #f8f9fa;
        }
        .notice-type-options .form-check-input:checked + .form-check-label {
            color: #007bff;
            font-weight: 600;
        }
        .notice-type-options .form-check:has(.form-check-input:checked) {
            border-color: #007bff;
            background: #e7f1ff;
        }
    </style>
</head>
<body>
    <div class="evidence-card">
        <div class="evidence-header">
            <h3><i class="fas fa-clock"></i> Late Arrival Evidence</h3>
            <p class="mb-0"><?php echo htmlspecialchars($siteName); ?></p>
        </div>

        <div class="evidence-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- User and Late Info -->
            <div class="info-box">
                <div class="row">
                    <div class="col-6">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($record['full_name']); ?>
                    </div>
                    <div class="col-6">
                        <strong>Date:</strong><br>
                        <?php echo date('F j, Y', strtotime($record['shift_date'])); ?>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-4">
                        <strong>Scheduled:</strong><br>
                        <?php echo date('h:i A', strtotime($record['scheduled_start'])); ?>
                    </div>
                    <div class="col-4">
                        <strong>Actual:</strong><br>
                        <?php echo $record['actual_start'] ? date('h:i A', strtotime($record['actual_start'])) : 'No clock-in'; ?>
                    </div>
                    <div class="col-4">
                        <strong>Late By:</strong><br>
                        <span class="text-danger fw-bold"><?php echo $record['late_minutes']; ?> min</span>
                    </div>
                </div>
            </div>

            <?php if ($deadlinePassed): ?>
                <!-- Deadline Expired -->
                <div class="info-box deadline-expired">
                    <h5><i class="fas fa-times-circle"></i> Submission Deadline Passed</h5>
                    <p class="mb-0">
                        The deadline to submit evidence was
                        <strong><?php echo date('F j, Y g:i A', strtotime($record['evidence_deadline'])); ?> (EST)</strong>.
                        Evidence can no longer be submitted for this late arrival.
                    </p>
                </div>

            <?php elseif ($alreadySubmitted): ?>
                <!-- Already Submitted -->
                <div class="info-box already-submitted">
                    <h5><i class="fas fa-check-circle"></i> Evidence Already Submitted</h5>
                    <p>Your evidence was submitted on
                        <strong><?php echo date('F j, Y g:i A', strtotime($record['evidence_uploaded_at'])); ?> (EST)</strong>.
                    </p>
                    <hr>
                    <p class="mb-1"><strong>Notice Type:</strong>
                        <?php
                        $noticeLabels = [
                            'email' => 'Email',
                            'chat' => 'Chat/Slack',
                            'sms' => 'SMS',
                            'phone' => 'Phone Call',
                            'prior_approval' => 'Prior Approval'
                        ];
                        echo $noticeLabels[$record['notice_type']] ?? ucfirst($record['notice_type']);
                        ?>
                    </p>
                    <?php if ($record['evidence_notes']): ?>
                        <p class="mb-1"><strong>Your Explanation:</strong><br>
                            <?php echo nl2br(htmlspecialchars($record['evidence_notes'])); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($record['evidence_file']): ?>
                        <p class="mb-0"><strong>Attached File:</strong>
                            <?php echo htmlspecialchars($record['evidence_file']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="text-center">
                    <p class="text-muted">HR will review your evidence and notify you of the decision.</p>
                </div>

            <?php else: ?>
                <!-- Submission Form -->
                <div class="info-box deadline-warning">
                    <strong><i class="fas fa-clock"></i> Deadline:</strong>
                    <?php echo date('F j, Y g:i A', strtotime($record['evidence_deadline'])); ?> (EST)
                    <br>
                    <small class="text-muted">
                        Time remaining: <?php
                        $remaining = strtotime($record['evidence_deadline']) - time();
                        $hours = floor($remaining / 3600);
                        $minutes = floor(($remaining % 3600) / 60);
                        echo "{$hours}h {$minutes}m";
                        ?>
                    </small>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <!-- Notice Type -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">How did you notify about being late? <span class="text-danger">*</span></label>
                        <div class="notice-type-options">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notice_type" id="type_email" value="email" required>
                                <label class="form-check-label" for="type_email">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <strong>Email</strong> - I sent an email to my supervisor/HR
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notice_type" id="type_chat" value="chat">
                                <label class="form-check-label" for="type_chat">
                                    <i class="fab fa-slack text-warning me-2"></i>
                                    <strong>Chat/Slack</strong> - I notified via chat application
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notice_type" id="type_sms" value="sms">
                                <label class="form-check-label" for="type_sms">
                                    <i class="fas fa-sms text-success me-2"></i>
                                    <strong>SMS</strong> - I sent a text message
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notice_type" id="type_phone" value="phone">
                                <label class="form-check-label" for="type_phone">
                                    <i class="fas fa-phone text-info me-2"></i>
                                    <strong>Phone Call</strong> - I called to notify
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notice_type" id="type_prior" value="prior_approval">
                                <label class="form-check-label" for="type_prior">
                                    <i class="fas fa-calendar-check text-secondary me-2"></i>
                                    <strong>Prior Approval</strong> - I had pre-approved late arrival
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Upload Evidence (Optional)</label>
                        <div class="file-upload-zone" id="dropZone">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <p class="mb-1">Drag and drop file here or click to browse</p>
                            <small class="text-muted">Screenshot of email/chat, PDF document (Max 5MB)</small>
                            <input type="file" name="evidence_file" id="evidenceFile" class="d-none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                        </div>
                        <div id="filePreview" class="mt-2 d-none">
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-file me-2"></i>
                                <span id="fileName"></span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="removeFile">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Explanation -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Brief Explanation</label>
                        <textarea name="explanation" class="form-control" rows="4"
                                  placeholder="Please provide a brief explanation of the circumstances..."><?php echo htmlspecialchars($_POST['explanation'] ?? ''); ?></textarea>
                        <small class="text-muted">Provide context about your late arrival and the notice you gave.</small>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane"></i> Submit Evidence
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="text-center py-3 text-muted">
            <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?></small>
        </div>
    </div>

    <script>
        // File upload handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('evidenceFile');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const removeFile = document.getElementById('removeFile');

        if (dropZone) {
            dropZone.addEventListener('click', () => fileInput.click());

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    updateFilePreview();
                }
            });

            fileInput.addEventListener('change', updateFilePreview);

            removeFile.addEventListener('click', () => {
                fileInput.value = '';
                filePreview.classList.add('d-none');
                dropZone.classList.remove('d-none');
            });

            function updateFilePreview() {
                if (fileInput.files.length) {
                    fileName.textContent = fileInput.files[0].name;
                    filePreview.classList.remove('d-none');
                    dropZone.classList.add('d-none');
                }
            }
        }
    </script>
</body>
</html>
