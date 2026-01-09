<?php
/**
 * Phone Calls Module - Main Dashboard View
 */

// Check make calls permission
$canMakeCalls = has_permission('phone_calls-make');

// Load models if available
if (file_exists(__DIR__ . '/../models/PhoneCallSettings.php')) {
    require_once __DIR__ . '/../models/PhoneCallSettings.php';
    $settings = new PhoneCallSettings($db);
    $isConfigured = $settings->isConfigured();
} else {
    $isConfigured = false;
}

// Get recent calls if model exists
$recentCalls = [];
if (file_exists(__DIR__ . '/../models/PhoneCall.php') && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../models/PhoneCall.php';
    $phoneCallModel = new PhoneCall($db);
    $recentCalls = $phoneCallModel->getRecent($_SESSION['user_id'], 5);
}
?>

<!-- Module CSS -->
<link rel="stylesheet" href="assets/css/phone-calls.css">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-phone-alt me-2"></i>Phone Calls
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item active">Phone Calls</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (!$isConfigured): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Configuration Required:</strong> Twilio settings are not configured.
                    <?php if (has_permission('phone_calls-settings')): ?>
                        <a href="settings.php" class="alert-link">Configure now</a>
                    <?php else: ?>
                        Please contact an administrator.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$canMakeCalls): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You don't have permission to make calls. Contact an administrator if you need this access.
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-5 col-xl-4">
                    <!-- Phone Dialer -->
                    <div class="phone-dialer">
                        <div class="card">
                            <!-- Display -->
                            <div class="phone-display">
                                <div class="status" id="phoneStatus">
                                    <span class="status-dot" id="statusDot"></span>
                                    <span id="statusText">Initializing...</span>
                                </div>
                                <div class="number-input" id="phoneDisplay">
                                    <span id="displayNumber"></span>
                                </div>
                                <div class="call-timer" id="callTimer" style="display:none;">0:00</div>
                                <div class="call-info" id="callInfo" style="display:none;"></div>
                            </div>

                            <!-- Input with Country Selector -->
                            <div class="phone-input-wrapper" id="inputWrapper">
                                <div class="country-phone-input">
                                    <div class="country-selector" id="countrySelector">
                                        <button type="button" class="country-btn" id="countryBtn" <?php echo (!$canMakeCalls || !$isConfigured) ? 'disabled' : ''; ?>>
                                            <span class="country-flag" id="selectedFlag">🇺🇸</span>
                                            <i class="fas fa-chevron-down"></i>
                                        </button>
                                        <div class="country-dropdown" id="countryDropdown">
                                            <div class="country-option selected" data-country="US" data-prefix="+1" data-flag="🇺🇸">
                                                <span class="country-flag">🇺🇸</span>
                                                <span class="country-name">United States</span>
                                                <span class="country-prefix">+1</span>
                                            </div>
                                            <div class="country-option" data-country="PH" data-prefix="+63" data-flag="🇵🇭">
                                                <span class="country-flag">🇵🇭</span>
                                                <span class="country-name">Philippines</span>
                                                <span class="country-prefix">+63</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="phone-input-group">
                                        <span class="phone-prefix" id="phonePrefix">+1</span>
                                        <input type="tel" class="form-control" id="phoneNumber"
                                               placeholder="(555) 123-4567"
                                               <?php echo (!$canMakeCalls || !$isConfigured) ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>

                            <!-- Keypad -->
                            <div class="phone-keypad" id="keypad">
                                <button class="key" data-digit="1"><span>1</span><span class="letters">&nbsp;</span></button>
                                <button class="key" data-digit="2"><span>2</span><span class="letters">ABC</span></button>
                                <button class="key" data-digit="3"><span>3</span><span class="letters">DEF</span></button>
                                <button class="key" data-digit="4"><span>4</span><span class="letters">GHI</span></button>
                                <button class="key" data-digit="5"><span>5</span><span class="letters">JKL</span></button>
                                <button class="key" data-digit="6"><span>6</span><span class="letters">MNO</span></button>
                                <button class="key" data-digit="7"><span>7</span><span class="letters">PQRS</span></button>
                                <button class="key" data-digit="8"><span>8</span><span class="letters">TUV</span></button>
                                <button class="key" data-digit="9"><span>9</span><span class="letters">WXYZ</span></button>
                                <button class="key" data-digit="*"><span>*</span><span class="letters">&nbsp;</span></button>
                                <button class="key" data-digit="0"><span>0</span><span class="letters">+</span></button>
                                <button class="key" data-digit="#"><span>#</span><span class="letters">&nbsp;</span></button>
                            </div>

                            <!-- Call Buttons -->
                            <div class="phone-actions">
                                <button type="button" class="btn-call btn-call-start" id="btnCall"
                                        <?php echo (!$canMakeCalls || !$isConfigured) ? 'disabled' : ''; ?>
                                        title="Make Call">
                                    <i class="fas fa-phone"></i>
                                </button>
                                <button type="button" class="btn-call btn-call-end" id="btnHangup" style="display:none;" title="End Call">
                                    <i class="fas fa-phone-slash"></i>
                                </button>
                            </div>

                            <!-- In-Call Controls -->
                            <div class="in-call-controls" id="inCallControls" style="display:none;">
                                <button class="control-btn" id="btnMute" title="Mute">
                                    <i class="fas fa-microphone"></i>
                                </button>
                                <button class="control-btn" id="btnKeypad" title="Keypad">
                                    <i class="fas fa-th"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-xl-8">
                    <!-- Recent Calls -->
                    <div class="card recent-calls">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history me-2"></i>Recent Calls
                            </h3>
                            <div class="card-tools">
                                <a href="history.php" class="btn btn-sm btn-outline-primary">
                                    View All <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentCalls)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-phone-slash fa-3x mb-3 opacity-50"></i>
                                    <p>No recent calls</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentCalls as $call): ?>
                                    <div class="call-item" data-number="<?php echo htmlspecialchars($call['to_number']); ?>">
                                        <div class="call-icon <?php echo $call['direction']; ?>">
                                            <?php if ($call['direction'] === 'outbound'): ?>
                                                <i class="fas fa-phone-alt"></i>
                                            <?php else: ?>
                                                <i class="fas fa-phone-volume"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="call-details">
                                            <div class="call-number">
                                                <?php echo htmlspecialchars($call['to_number'] ?: $call['from_number']); ?>
                                            </div>
                                            <div class="call-time">
                                                <?php echo date('M j, Y g:i A', strtotime($call['created_at'])); ?>
                                                &middot;
                                                <?php echo ucfirst($call['status']); ?>
                                            </div>
                                        </div>
                                        <div class="call-duration">
                                            <?php echo PhoneCall::formatDuration($call['duration']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <?php if (has_permission('phone_calls-history')): ?>
                        <?php
                        $stats = isset($phoneCallModel) ? $phoneCallModel->getUserStats($_SESSION['user_id'], 'month') : null;
                        ?>
                        <?php if ($stats): ?>
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="card stats-card">
                                        <div class="card-body d-flex align-items-center">
                                            <div class="stats-icon bg-calls me-3">
                                                <i class="fas fa-phone"></i>
                                            </div>
                                            <div>
                                                <div class="stats-value"><?php echo (int)$stats['total_calls']; ?></div>
                                                <div class="stats-label">Calls This Month</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stats-card">
                                        <div class="card-body d-flex align-items-center">
                                            <div class="stats-icon bg-duration me-3">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div>
                                                <div class="stats-value"><?php echo PhoneCall::formatDuration($stats['total_duration'] ?? 0); ?></div>
                                                <div class="stats-label">Total Duration</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card stats-card">
                                        <div class="card-body d-flex align-items-center">
                                            <div class="stats-icon bg-cost me-3">
                                                <i class="fas fa-dollar-sign"></i>
                                            </div>
                                            <div>
                                                <div class="stats-value"><?php echo PhoneCall::formatCost($stats['total_cost'] ?? 0); ?></div>
                                                <div class="stats-label">Estimated Cost</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canMakeCalls && $isConfigured): ?>
<!-- Twilio Voice SDK v2.x -->
<script src="assets/js/twilio-voice-sdk.min.js"></script>
<script src="assets/js/phone-client.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if Twilio SDK loaded
    if (typeof Twilio === 'undefined') {
        document.getElementById('statusText').textContent = 'SDK loading failed. Please refresh.';
        document.getElementById('statusDot').className = 'status-dot error';
        return;
    }
    // DOM Elements
    const phoneNumber = document.getElementById('phoneNumber');
    const displayNumber = document.getElementById('displayNumber');
    const statusDot = document.getElementById('statusDot');
    const statusText = document.getElementById('statusText');
    const btnCall = document.getElementById('btnCall');
    const btnHangup = document.getElementById('btnHangup');
    const btnMute = document.getElementById('btnMute');
    const btnKeypad = document.getElementById('btnKeypad');
    const callTimer = document.getElementById('callTimer');
    const callInfo = document.getElementById('callInfo');
    const inputWrapper = document.getElementById('inputWrapper');
    const inCallControls = document.getElementById('inCallControls');
    const keypad = document.getElementById('keypad');

    // Country selector elements
    const countrySelector = document.getElementById('countrySelector');
    const countryBtn = document.getElementById('countryBtn');
    const countryDropdown = document.getElementById('countryDropdown');
    const selectedFlag = document.getElementById('selectedFlag');
    const phonePrefix = document.getElementById('phonePrefix');
    const countryOptions = document.querySelectorAll('.country-option');

    let phoneClient = null;
    let isOnCall = false;
    let selectedCountry = 'US';
    let currentPrefix = '+1';

    // Country selector functionality
    countryBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        countrySelector.classList.toggle('open');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!countrySelector.contains(e.target)) {
            countrySelector.classList.remove('open');
        }
    });

    // Handle country selection
    countryOptions.forEach(option => {
        option.addEventListener('click', function() {
            const country = this.dataset.country;
            const prefix = this.dataset.prefix;
            const flag = this.dataset.flag;

            // Update selected country
            selectedCountry = country;
            currentPrefix = prefix;
            selectedFlag.textContent = flag;
            phonePrefix.textContent = prefix;

            // Update selected state
            countryOptions.forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');

            // Update input padding for longer prefix
            if (prefix === '+63') {
                phoneNumber.classList.add('prefix-63');
                phoneNumber.placeholder = '9XX XXX XXXX';
            } else {
                phoneNumber.classList.remove('prefix-63');
                phoneNumber.placeholder = '(555) 123-4567';
            }

            // Close dropdown and focus input
            countrySelector.classList.remove('open');
            phoneNumber.focus();
        });
    });

    // Auto-detect country from input and strip prefix
    phoneNumber.addEventListener('input', function() {
        let value = this.value;

        // If user types +63, switch to Philippines
        if (value.startsWith('+63') || value.startsWith('63')) {
            if (selectedCountry !== 'PH') {
                selectedCountry = 'PH';
                currentPrefix = '+63';
                selectedFlag.textContent = '🇵🇭';
                phonePrefix.textContent = '+63';
                phoneNumber.classList.add('prefix-63');
                phoneNumber.placeholder = '9XX XXX XXXX';
                countryOptions.forEach(opt => {
                    opt.classList.toggle('selected', opt.dataset.country === 'PH');
                });
            }
            // Remove the prefix from input
            this.value = value.replace(/^\+?63\s*/, '');
        }
        // If user types +1, switch to US
        else if (value.startsWith('+1') || (value.startsWith('1') && value.length > 10)) {
            if (selectedCountry !== 'US') {
                selectedCountry = 'US';
                currentPrefix = '+1';
                selectedFlag.textContent = '🇺🇸';
                phonePrefix.textContent = '+1';
                phoneNumber.classList.remove('prefix-63');
                phoneNumber.placeholder = '(555) 123-4567';
                countryOptions.forEach(opt => {
                    opt.classList.toggle('selected', opt.dataset.country === 'US');
                });
            }
            // Remove the prefix from input
            this.value = value.replace(/^\+?1\s*/, '');
        }

        // Update display
        updateDisplayNumber();
    });

    // Format and update display number
    function updateDisplayNumber() {
        const value = phoneNumber.value.replace(/\D/g, '');
        if (value) {
            displayNumber.textContent = currentPrefix + ' ' + formatPhoneNumber(value);
        } else {
            displayNumber.textContent = '';
        }
    }

    // Get full phone number with prefix for calling
    function getFullPhoneNumber() {
        const value = phoneNumber.value.replace(/\D/g, '');
        return currentPrefix + value;
    }

    // Initialize Phone Client
    phoneClient = new PhoneClient({
        onReady: function() {
            updateStatus('ready', 'Ready to call');
        },
        onError: function(error) {
            updateStatus('error', error.message || 'Error');
            showAlert('danger', error.message || 'An error occurred');
        },
        onConnect: function(call) {
            isOnCall = true;
            updateUIForCall(true);
            updateStatus('connected', 'Connected');
        },
        onDisconnect: function(call) {
            isOnCall = false;
            updateUIForCall(false);
            updateStatus('ready', 'Ready to call');
            btnCall.disabled = false;
        },
        onStatus: function(status, message) {
            updateStatus(status, message);
        }
    });

    // Auto-focus phone input when user types numbers anywhere on page
    document.addEventListener('keydown', function(e) {
        // Only if not already focused on an input and not on a call
        if (document.activeElement.tagName !== 'INPUT' &&
            document.activeElement.tagName !== 'TEXTAREA' &&
            !isOnCall) {
            // Check if key is a number, +, or backspace
            if (/^[0-9+]$/.test(e.key) || e.key === 'Backspace') {
                phoneNumber.focus();
                // For number keys, the input will receive the key automatically
            }
        }
    });

    // Also focus on Enter to call
    phoneNumber.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && phoneNumber.value.trim() && !isOnCall) {
            e.preventDefault();
            btnCall.click();
        }
    });

    // Update status display
    function updateStatus(status, message) {
        statusDot.className = 'status-dot ' + status;
        statusText.textContent = message;
    }

    // Update UI for call state
    function updateUIForCall(onCall) {
        if (onCall) {
            btnCall.style.display = 'none';
            btnHangup.style.display = 'flex';
            inputWrapper.style.display = 'none';
            callTimer.style.display = 'block';
            callInfo.style.display = 'block';
            callInfo.textContent = currentPrefix + ' ' + formatPhoneNumber(phoneNumber.value);
            inCallControls.style.display = 'flex';
        } else {
            btnCall.style.display = 'flex';
            btnHangup.style.display = 'none';
            inputWrapper.style.display = 'block';
            callTimer.style.display = 'none';
            callTimer.textContent = '0:00';
            callInfo.style.display = 'none';
            inCallControls.style.display = 'none';
            btnMute.classList.remove('active');
            btnMute.querySelector('i').className = 'fas fa-microphone';
        }
    }

    // Call timer event
    document.addEventListener('phoneCallTimer', function(e) {
        callTimer.textContent = e.detail.display;
    });

    // Make call
    btnCall.addEventListener('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();

        const rawNumber = phoneNumber.value.trim();
        if (!rawNumber) {
            showAlert('warning', 'Please enter a phone number');
            return;
        }

        // Check if Philippines (under development)
        if (selectedCountry === 'PH') {
            showAlert('info', 'Philippines calling is under development. Coming soon!');
            return;
        }

        // Get full number with country prefix
        const fullNumber = getFullPhoneNumber();

        try {
            btnCall.disabled = true;
            updateStatus('connecting', 'Connecting...');
            await phoneClient.call(fullNumber);
        } catch (error) {
            showAlert('danger', error.message || 'Call failed');
            updateStatus('error', error.message || 'Call failed');
            btnCall.disabled = false;
        }
    });

    // Hang up
    btnHangup.addEventListener('click', function() {
        phoneClient.hangup();
    });

    // Mute toggle
    btnMute.addEventListener('click', function() {
        const muted = phoneClient.toggleMute();
        btnMute.classList.toggle('active', muted);
        btnMute.querySelector('i').className = muted ? 'fas fa-microphone-slash' : 'fas fa-microphone';
    });

    // Keypad toggle
    btnKeypad.addEventListener('click', function() {
        btnKeypad.classList.toggle('active');
        keypad.style.display = btnKeypad.classList.contains('active') ? 'grid' : 'none';
    });

    // Keypad keys
    document.querySelectorAll('.phone-keypad .key').forEach(function(key) {
        key.addEventListener('click', function() {
            const digit = this.dataset.digit;
            if (isOnCall) {
                phoneClient.sendDigit(digit);
            } else {
                phoneNumber.value += digit;
                displayNumber.textContent = formatPhoneNumber(phoneNumber.value);
            }
        });
    });

    // Phone number input
    phoneNumber.addEventListener('input', function() {
        displayNumber.textContent = formatPhoneNumber(this.value);
    });

    // Click recent call to dial
    document.querySelectorAll('.call-item').forEach(function(item) {
        item.addEventListener('click', function() {
            const number = this.dataset.number;
            if (number && !isOnCall) {
                phoneNumber.value = number;
                displayNumber.textContent = formatPhoneNumber(number);
            }
        });
    });

    // Enter key to call
    phoneNumber.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !isOnCall) {
            btnCall.click();
        }
    });

    // Show alert
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 5000);
    }
});
</script>
<?php endif; ?>
