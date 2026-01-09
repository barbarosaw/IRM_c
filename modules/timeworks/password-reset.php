<?php
/**
 * TimeWorks Module - Self-Service Password Reset
 *
 * Public page for TimeWorks users to reset their password
 * No authentication required
 *
 * @author ikinciadam@gmail.com
 */

// This is a standalone public page - no session required
$pageTitle = 'Reset Your Password - TimeWorks';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-container {
            max-width: 450px;
            width: 100%;
        }

        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .reset-header {
            background: var(--primary-gradient);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .reset-header h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 600;
        }

        .reset-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .reset-body {
            padding: 30px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 10px;
            transition: all 0.3s ease;
            position: relative;
        }

        .step.active {
            background: var(--primary-gradient);
            color: white;
            transform: scale(1.1);
        }

        .step.completed {
            background: #28a745;
            color: white;
        }

        .step-line {
            width: 40px;
            height: 3px;
            background: #e9ecef;
            align-self: center;
        }

        .step-line.completed {
            background: #28a745;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary-custom:disabled {
            opacity: 0.7;
            transform: none;
        }

        .code-input {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            letter-spacing: 8px;
            text-align: center;
            text-transform: uppercase;
        }

        .password-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin: 5px 0;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .requirement i {
            width: 20px;
            margin-right: 8px;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.valid i {
            color: #28a745;
        }

        .alert {
            border-radius: 10px;
        }

        .countdown {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
            margin-top: 10px;
        }

        .countdown.warning {
            color: #dc3545;
        }

        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .resend-link {
            font-size: 0.85rem;
            color: #667eea;
            cursor: pointer;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .resend-link.disabled {
            color: #6c757d;
            cursor: not-allowed;
        }

        .domain-options {
            display: flex;
            gap: 20px;
        }

        .domain-options .form-check {
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .domain-options .form-check:hover {
            border-color: #667eea;
        }

        .domain-options .form-check-input:checked + .form-check-label {
            color: #667eea;
            font-weight: 600;
        }

        .domain-options .form-check:has(.form-check-input:checked) {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .attempts-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 10px 15px;
            margin-top: 15px;
            font-size: 0.85rem;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }

        .input-group-password {
            position: relative;
        }

        .input-group-password .form-control {
            padding-right: 45px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <h1><i class="fas fa-key me-2"></i>Reset Your Password</h1>
                <p>TimeWorks Password Recovery</p>
            </div>
            <div class="reset-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step-ind-1">1</div>
                    <div class="step-line" id="line-1"></div>
                    <div class="step" id="step-ind-2">2</div>
                    <div class="step-line" id="line-2"></div>
                    <div class="step" id="step-ind-3">3</div>
                </div>

                <!-- Alert Container -->
                <div id="alert-container"></div>

                <!-- Step 1: Email Input -->
                <div class="step-content active" id="step-1">
                    <h5 class="mb-3">Enter your email address</h5>
                    <p class="text-muted mb-4">We'll send you a verification code to reset your password.</p>

                    <form id="email-form">
                        <div class="mb-3">
                            <label for="email" class="form-label">Username</label>
                            <input type="text" class="form-control" id="email" name="email" placeholder="your.username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Domain</label>
                            <div class="domain-options">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="domain" id="domain-abroadworks" value="@abroadworks.com">
                                    <label class="form-check-label" for="domain-abroadworks">
                                        @abroadworks.com
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="domain" id="domain-chabadworks" value="@chabadworks.com">
                                    <label class="form-check-label" for="domain-chabadworks">
                                        @chabadworks.com
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100" id="btn-send-code">
                            <i class="fas fa-paper-plane me-2"></i>Send Verification Code
                        </button>
                    </form>
                </div>

                <!-- Step 2: Code Verification -->
                <div class="step-content" id="step-2">
                    <h5 class="mb-3">Enter verification code</h5>
                    <p class="text-muted mb-2">We sent a code to <strong id="email-display"></strong></p>

                    <form id="code-form">
                        <div class="mb-3">
                            <label for="code" class="form-label">Verification Code</label>
                            <input type="text" class="form-control code-input" id="code" name="code"
                                   placeholder="A1B2C3D4" maxlength="8" autocomplete="off" required>
                        </div>

                        <div class="countdown" id="countdown">
                            <i class="fas fa-clock me-1"></i>Code expires in <span id="countdown-time">30:00</span>
                        </div>

                        <div class="attempts-warning" id="attempts-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <span id="attempts-text"></span>
                        </div>

                        <button type="submit" class="btn btn-primary-custom w-100 mt-3" id="btn-verify-code">
                            <i class="fas fa-check me-2"></i>Verify Code
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <span class="resend-link disabled" id="resend-link">
                            <i class="fas fa-redo me-1"></i>Resend code (<span id="resend-countdown">120</span>s)
                        </span>
                    </div>
                </div>

                <!-- Step 3: New Password -->
                <div class="step-content" id="step-3">
                    <h5 class="mb-3">Create new password</h5>
                    <p class="text-muted mb-4">Choose a strong password for your account.</p>

                    <form id="password-form">
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <div class="input-group-password">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <span class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Confirm Password</label>
                            <div class="input-group-password">
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                <span class="password-toggle" onclick="togglePassword('password_confirm')">
                                    <i class="fas fa-eye" id="password_confirm-icon"></i>
                                </span>
                            </div>
                        </div>

                        <div class="password-requirements">
                            <small class="text-muted d-block mb-2"><strong>Password must have:</strong></small>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-times-circle"></i> At least 8 characters
                            </div>
                            <div class="requirement" id="req-upper">
                                <i class="fas fa-times-circle"></i> At least 1 uppercase letter
                            </div>
                            <div class="requirement" id="req-lower">
                                <i class="fas fa-times-circle"></i> At least 1 lowercase letter
                            </div>
                            <div class="requirement" id="req-number">
                                <i class="fas fa-times-circle"></i> At least 1 number
                            </div>
                            <div class="requirement" id="req-special">
                                <i class="fas fa-times-circle"></i> At least 1 special character
                            </div>
                            <div class="requirement" id="req-match">
                                <i class="fas fa-times-circle"></i> Passwords match
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary-custom w-100 mt-3" id="btn-set-password" disabled>
                            <i class="fas fa-lock me-2"></i>Change Password
                        </button>
                    </form>
                </div>

                <!-- Step 4: Success -->
                <div class="step-content" id="step-4">
                    <div class="text-center">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="text-success mb-3">Password Changed!</h4>
                        <p class="text-muted mb-4">Your password has been successfully updated. You can now log in with your new password.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="faq.php" target="_blank" class="btn btn-primary btn-lg mb-2 px-4 py-2" style="font-size: 1.1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                <i class="fas fa-question-circle me-2"></i>Need Help? View FAQ ↗
            </a>
            <br>
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1"></i>Secure password reset by AbroadWorks
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // State
        let currentStep = 1;
        let userEmail = '';
        let resetToken = '';
        let codeExpiresAt = null;
        let resendAvailableAt = null;
        let countdownInterval = null;
        let resendInterval = null;

        // API Base URL
        const API_BASE = 'api/public/';

        // Show alert
        function showAlert(message, type = 'danger') {
            const container = document.getElementById('alert-container');
            container.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }

        // Clear alerts
        function clearAlerts() {
            document.getElementById('alert-container').innerHTML = '';
        }

        // Go to step
        function goToStep(step) {
            // Update step indicators
            for (let i = 1; i <= 3; i++) {
                const stepInd = document.getElementById(`step-ind-${i}`);
                const line = document.getElementById(`line-${i}`);

                if (i < step) {
                    stepInd.classList.remove('active');
                    stepInd.classList.add('completed');
                    stepInd.innerHTML = '<i class="fas fa-check"></i>';
                    if (line) line.classList.add('completed');
                } else if (i === step) {
                    stepInd.classList.add('active');
                    stepInd.classList.remove('completed');
                    stepInd.innerHTML = i;
                } else {
                    stepInd.classList.remove('active', 'completed');
                    stepInd.innerHTML = i;
                    if (line) line.classList.remove('completed');
                }
            }

            // Show/hide step content
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.getElementById(`step-${step}`).classList.add('active');

            currentStep = step;
            clearAlerts();
        }

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Start countdown
        function startCountdown() {
            codeExpiresAt = Date.now() + (30 * 60 * 1000); // 30 minutes
            resendAvailableAt = Date.now() + (120 * 1000); // 2 minutes

            countdownInterval = setInterval(() => {
                const remaining = codeExpiresAt - Date.now();
                const countdownEl = document.getElementById('countdown-time');
                const countdownContainer = document.getElementById('countdown');

                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    countdownEl.textContent = 'Expired';
                    countdownContainer.classList.add('warning');
                    showAlert('Verification code has expired. Please request a new code.', 'warning');
                    return;
                }

                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);
                countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                if (remaining < 300000) { // Less than 5 minutes
                    countdownContainer.classList.add('warning');
                }
            }, 1000);

            // Resend countdown
            resendInterval = setInterval(() => {
                const remaining = resendAvailableAt - Date.now();
                const resendLink = document.getElementById('resend-link');
                const resendCountdown = document.getElementById('resend-countdown');

                if (remaining <= 0) {
                    clearInterval(resendInterval);
                    resendLink.classList.remove('disabled');
                    resendLink.innerHTML = '<i class="fas fa-redo me-1"></i>Resend code';
                    return;
                }

                const seconds = Math.ceil(remaining / 1000);
                resendCountdown.textContent = seconds;
            }, 1000);
        }

        // Validate password
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;

            const checks = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()_+\-=\[\]{}|;:'",.<>\/?\\`~]/.test(password),
                match: password === confirm && password.length > 0
            };

            // Update UI
            Object.keys(checks).forEach(key => {
                const el = document.getElementById(`req-${key}`);
                const icon = el.querySelector('i');

                if (checks[key]) {
                    el.classList.add('valid');
                    icon.classList.remove('fa-times-circle');
                    icon.classList.add('fa-check-circle');
                } else {
                    el.classList.remove('valid');
                    icon.classList.remove('fa-check-circle');
                    icon.classList.add('fa-times-circle');
                }
            });

            // Enable/disable submit button
            const allValid = Object.values(checks).every(v => v);
            document.getElementById('btn-set-password').disabled = !allValid;

            return allValid;
        }

        // Step 1: Send code
        document.getElementById('email-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('btn-send-code');
            const username = document.getElementById('email').value.trim().toLowerCase();
            const selectedDomain = document.querySelector('input[name="domain"]:checked');

            if (!username) {
                showAlert('Please enter your username.', 'warning');
                return;
            }

            if (username.includes('@')) {
                showAlert('Please enter only your username without "@". Select your domain from the options below.', 'warning');
                return;
            }

            if (!selectedDomain) {
                showAlert('Please select a domain.', 'warning');
                return;
            }

            const email = username + selectedDomain.value;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';

            try {
                const response = await fetch(API_BASE + 'send-reset-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });

                const data = await response.json();

                if (data.success) {
                    userEmail = email;
                    document.getElementById('email-display').textContent = email;
                    goToStep(2);
                    startCountdown();
                } else if (data.wait_seconds) {
                    showAlert(`Please wait ${data.wait_seconds} seconds before requesting a new code.`, 'warning');
                } else {
                    // For security, still go to step 2 even if email not found
                    userEmail = email;
                    document.getElementById('email-display').textContent = email;
                    goToStep(2);
                    startCountdown();
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Verification Code';
            }
        });

        // Step 2: Verify code
        document.getElementById('code-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = document.getElementById('btn-verify-code');
            const code = document.getElementById('code').value.trim().toUpperCase();

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';

            try {
                const response = await fetch(API_BASE + 'verify-reset-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: userEmail, code })
                });

                const data = await response.json();

                if (data.success) {
                    resetToken = data.reset_token;
                    clearInterval(countdownInterval);
                    clearInterval(resendInterval);
                    goToStep(3);
                } else {
                    if (data.remaining_attempts !== undefined) {
                        const warning = document.getElementById('attempts-warning');
                        const text = document.getElementById('attempts-text');
                        warning.style.display = 'block';
                        text.textContent = `${data.remaining_attempts} attempt(s) remaining`;
                    }

                    if (data.max_attempts_reached || data.expired) {
                        showAlert(data.message + ' <a href="javascript:location.reload()">Start over</a>', 'danger');
                        document.getElementById('btn-verify-code').disabled = true;
                    } else {
                        showAlert(data.message, 'danger');
                    }
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Verify Code';
            }
        });

        // Step 3: Set password
        document.getElementById('password-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!validatePassword()) {
                showAlert('Please meet all password requirements.', 'warning');
                return;
            }

            const btn = document.getElementById('btn-set-password');
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Changing Password...';

            try {
                const response = await fetch(API_BASE + 'set-new-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        reset_token: resetToken,
                        password,
                        password_confirm: passwordConfirm
                    })
                });

                const data = await response.json();

                if (data.success) {
                    goToStep(4);
                } else {
                    if (data.errors) {
                        showAlert(data.errors.join('<br>'), 'danger');
                    } else {
                        showAlert(data.message, 'danger');
                    }
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-lock me-2"></i>Change Password';
            }
        });

        // Password validation on input
        document.getElementById('password').addEventListener('input', validatePassword);
        document.getElementById('password_confirm').addEventListener('input', validatePassword);

        // Code input formatting
        document.getElementById('code').addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });

        // Resend code
        document.getElementById('resend-link').addEventListener('click', async () => {
            const link = document.getElementById('resend-link');
            if (link.classList.contains('disabled')) return;

            link.classList.add('disabled');
            link.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';

            try {
                const response = await fetch(API_BASE + 'send-reset-code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: userEmail })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('A new code has been sent to your email.', 'success');
                    document.getElementById('code').value = '';
                    document.getElementById('attempts-warning').style.display = 'none';

                    // Reset countdowns
                    clearInterval(countdownInterval);
                    clearInterval(resendInterval);
                    document.getElementById('countdown').classList.remove('warning');
                    startCountdown();
                } else if (data.wait_seconds) {
                    showAlert(`Please wait ${data.wait_seconds} seconds before requesting a new code.`, 'warning');
                    link.classList.remove('disabled');
                    link.innerHTML = '<i class="fas fa-redo me-1"></i>Resend code';
                }
            } catch (error) {
                showAlert('Failed to resend code. Please try again.', 'danger');
                link.classList.remove('disabled');
                link.innerHTML = '<i class="fas fa-redo me-1"></i>Resend code';
            }
        });
    </script>
</body>
</html>
