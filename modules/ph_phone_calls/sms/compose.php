<?php
/**
 * PH Communications Module - Compose SMS
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Check permission
if (!has_permission('ph_communications-send-sms')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$page_title = "Send SMS";
$root_path = "../../../";

include '../../../components/header.php';
include '../../../components/sidebar.php';
?>

<link rel="stylesheet" href="../assets/css/ph-communications.css">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-paper-plane me-2"></i>Send SMS
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="../index.php">PH Communications</a></li>
                        <li class="breadcrumb-item active">Send SMS</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Alert Container -->
            <div id="alertContainer"></div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-sms me-2"></i>New SMS Message
                            </h3>
                        </div>
                        <div class="card-body">
                            <form id="smsForm">
                                <div class="mb-3">
                                    <label for="toNumber" class="form-label">Recipient Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="toNumber" name="to_number"
                                           placeholder="+639XXXXXXXXX or 09XXXXXXXXX" required>
                                    <small class="form-text text-muted">
                                        Philippines mobile number (formats: +639XX, 639XX, 09XX, 9XX)
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="5"
                                              maxlength="1530" placeholder="Type your message here..." required></textarea>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="form-text text-muted">
                                            <span id="charCount">0</span> / 1530 characters
                                            (<span id="smsCount">0</span> SMS)
                                        </small>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> 160 chars = 1 SMS, 306 chars = 2 SMS, etc.
                                        </small>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg" id="btnSend">
                                        <i class="fas fa-paper-plane me-1"></i> Send SMS
                                    </button>
                                    <a href="outbox.php" class="btn btn-outline-secondary btn-lg ms-2">
                                        <i class="fas fa-list me-1"></i> View Sent Messages
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card bg-light">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>Quick Tips
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li class="mb-2">Messages are limited to 1530 characters</li>
                                <li class="mb-2">1 SMS = 160 characters (GSM-7 encoding)</li>
                                <li class="mb-2">Long messages are split automatically</li>
                                <li class="mb-2">Delivery reports are tracked automatically</li>
                                <li class="mb-2">Supports Globe, Smart, Sun, and DITO networks</li>
                            </ul>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-phone-alt me-2"></i>Phone Number Formats
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="small mb-2">All these formats are accepted:</p>
                            <ul class="small mb-0">
                                <li>+639171234567</li>
                                <li>639171234567</li>
                                <li>09171234567</li>
                                <li>9171234567</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageField = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    const smsCount = document.getElementById('smsCount');
    const smsForm = document.getElementById('smsForm');
    const btnSend = document.getElementById('btnSend');

    // Update character and SMS count
    messageField.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;

        // Calculate SMS count (160 chars for first SMS, 153 for each additional)
        let count = 0;
        if (length === 0) {
            count = 0;
        } else if (length <= 160) {
            count = 1;
        } else {
            count = Math.ceil(length / 153);
        }
        smsCount.textContent = count;
    });

    // Handle form submission
    smsForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const toNumber = document.getElementById('toNumber').value.trim();
        const message = document.getElementById('message').value.trim();

        if (!toNumber || !message) {
            showAlert('warning', 'Please fill in all required fields');
            return;
        }

        // Disable button
        btnSend.disabled = true;
        btnSend.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Sending...';

        try {
            const response = await fetch('../api/m360-sms/send-sms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    to_number: toNumber,
                    message: message
                })
            });

            const result = await response.json();

            if (result.success) {
                showAlert('success', 'SMS sent successfully! Message ID: ' + (result.data.message_id || 'N/A'));
                smsForm.reset();
                charCount.textContent = '0';
                smsCount.textContent = '0';

                // Redirect to outbox after 2 seconds
                setTimeout(() => {
                    window.location.href = 'outbox.php';
                }, 2000);
            } else {
                showAlert('danger', result.error || 'Failed to send SMS');
            }
        } catch (error) {
            console.error('Send SMS error:', error);
            showAlert('danger', 'An error occurred while sending SMS');
        } finally {
            btnSend.disabled = false;
            btnSend.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Send SMS';
        }
    });

    function showAlert(type, message) {
        // Get or create alert container
        let container = document.getElementById('alertContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'alertContainer';
            const contentFluid = document.querySelector('.container-fluid');
            contentFluid.insertBefore(container, contentFluid.firstChild);
        }

        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible`;
        alertDiv.style.display = 'block';

        // Icon based on type
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };

        alertDiv.innerHTML = `
            <i class="fas fa-${icons[type] || 'info-circle'}"></i>
            <strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;

        // Add to container
        container.appendChild(alertDiv);

        // Auto-remove after 5 seconds with fade animation
        setTimeout(() => {
            alertDiv.classList.add('fade-out');
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 300); // Wait for animation to complete
        }, 5000);
    }
});
</script>

<?php include '../../../components/footer.php'; ?>
