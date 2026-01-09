/**
 * Phone Calls Module - Twilio Voice Client
 * Uses Twilio Voice SDK v2.x API
 */

class PhoneClient {
    constructor(options = {}) {
        this.device = null;
        this.currentCall = null;
        this.token = null;
        this.identity = null;
        this.iceServers = [];
        this.isReady = false;
        this.isMuted = false;

        // Callbacks
        this.onReady = options.onReady || (() => {});
        this.onError = options.onError || ((error) => console.error('PhoneClient Error:', error));
        this.onIncoming = options.onIncoming || (() => {});
        this.onConnect = options.onConnect || (() => {});
        this.onDisconnect = options.onDisconnect || (() => {});
        this.onStatus = options.onStatus || (() => {});

        // Timer
        this.callTimer = null;
        this.callStartTime = null;

        // Auto-initialize
        this.init();
    }

    /**
     * Initialize the client
     */
    async init() {
        try {
            if (typeof Twilio === 'undefined' || typeof Twilio.Device === 'undefined') {
                throw new Error('Twilio SDK not loaded. Please include the Twilio Voice SDK.');
            }

            await this.refreshToken();

            const deviceOptions = {
                codecPreferences: ['opus', 'pcmu'],
                enableRingingState: true,
                logLevel: 'warn',
                edge: 'roaming',
                allowIncomingWhileBusy: false
            };

            this.device = new Twilio.Device(this.token, deviceOptions);
            this.setupEventHandlers();
            await this.device.register();

        } catch (error) {
            this.onError(error);
            this.onStatus('error', error.message);
        }
    }

    /**
     * Refresh access token
     */
    async refreshToken() {
        try {
            const response = await fetch('api/token.php', {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to get token');
            }

            this.token = data.token;
            this.identity = data.identity;
            this.iceServers = data.iceServers || [];

            if (this.device) {
                this.device.updateToken(this.token);
            }

            return this.token;
        } catch (error) {
            throw new Error('Failed to get access token: ' + error.message);
        }
    }

    /**
     * Set up Twilio Device event handlers (v2.x API)
     */
    setupEventHandlers() {
        this.device.on('registered', () => {
            this.isReady = true;
            this.onReady();
            this.onStatus('ready', 'Ready to make calls');
        });

        this.device.on('error', (error) => {
            console.error('Device error:', error.message);
            this.onError(error);
            this.onStatus('error', error.message || 'Connection error');
        });

        this.device.on('unregistered', () => {
            this.isReady = false;
            this.onStatus('offline', 'Offline');
        });

        this.device.on('tokenWillExpire', async () => {
            try {
                await this.refreshToken();
            } catch (error) {
                console.error('Failed to refresh token:', error.message);
            }
        });

        this.device.on('incoming', (call) => {
            this.currentCall = call;
            this.setupCallHandlers(call);
            this.onIncoming(call);
            this.onStatus('incoming', 'Incoming call...');
        });
    }

    /**
     * Set up individual call event handlers
     */
    setupCallHandlers(call) {
        call.on('ringing', () => {
            this.onStatus('ringing', 'Ringing...');
        });

        call.on('accept', () => {
            this.startTimer();
            this.onConnect(call);
            this.onStatus('connected', 'Connected');
        });

        call.on('disconnect', () => {
            this.stopTimer();
            this.currentCall = null;
            this.isMuted = false;
            this.onDisconnect(call);
            this.onStatus('disconnected', 'Call ended');
        });

        call.on('cancel', () => {
            this.stopTimer();
            this.currentCall = null;
            this.onDisconnect(call);
            this.onStatus('cancelled', 'Call cancelled');
        });

        call.on('reject', () => {
            this.stopTimer();
            this.currentCall = null;
            this.onDisconnect(call);
            this.onStatus('rejected', 'Call rejected');
        });

        call.on('error', (error) => {
            console.error('Call error:', error.message);
            this.stopTimer();
            this.currentCall = null;
            this.onError(error);
            this.onStatus('error', error.message);
        });
    }

    /**
     * Make an outbound call
     */
    async call(phoneNumber) {
        if (!this.isReady) {
            throw new Error('Phone client not ready. Please wait...');
        }

        if (this.currentCall) {
            throw new Error('Already on a call');
        }

        // Validate phone number (US or Philippines)
        const cleaned = phoneNumber.replace(/\D/g, '');
        let formatted;

        // Check for Philippines number (+63)
        if (phoneNumber.startsWith('+63') || cleaned.startsWith('63')) {
            if (cleaned.startsWith('63') && cleaned.length === 12) {
                formatted = '+' + cleaned;
            } else if (cleaned.length === 10 && phoneNumber.startsWith('+63')) {
                formatted = '+63' + cleaned;
            } else {
                throw new Error('Invalid Philippines number. Format: +63 followed by 10 digits.');
            }
            if (!/^\+63[2-9][0-9]{9}$/.test(formatted)) {
                throw new Error('Invalid Philippines phone number format.');
            }
        }
        // Default to US number (+1)
        else {
            if (cleaned.length === 10) {
                formatted = '+1' + cleaned;
            } else if (cleaned.length === 11 && cleaned.startsWith('1')) {
                formatted = '+' + cleaned;
            } else if (phoneNumber.startsWith('+1') && cleaned.length === 11) {
                formatted = '+' + cleaned;
            } else {
                throw new Error('Invalid phone number. US: 10 digits, Philippines: +63 followed by 10 digits.');
            }
            if (!/^\+1[2-9][0-9]{9}$/.test(formatted)) {
                throw new Error('Invalid US phone number format.');
            }
        }

        try {
            this.onStatus('connecting', 'Connecting...');
            await this.requestMicrophonePermission();

            const connectOptions = {
                params: { To: formatted }
            };

            // Add TURN servers for restrictive networks
            if (this.iceServers && this.iceServers.length > 0) {
                connectOptions.rtcConfiguration = {
                    iceServers: this.iceServers,
                    iceTransportPolicy: 'relay'
                };
            }

            this.currentCall = await this.device.connect(connectOptions);
            this.setupCallHandlers(this.currentCall);

            return this.currentCall;
        } catch (error) {
            console.error('Call failed:', error.message);
            this.currentCall = null;
            throw error;
        }
    }

    /**
     * Request microphone permission
     */
    async requestMicrophonePermission() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (error) {
            if (error.name === 'NotAllowedError') {
                throw new Error('Microphone access denied. Please allow microphone access to make calls.');
            } else if (error.name === 'NotFoundError') {
                throw new Error('No microphone found. Please connect a microphone to make calls.');
            }
            throw error;
        }
    }

    /**
     * Hang up current call
     */
    hangup() {
        if (this.currentCall) {
            this.currentCall.disconnect();
            this.currentCall = null;
        }
        if (this.device) {
            this.device.disconnectAll();
        }
    }

    /**
     * Accept incoming call
     */
    accept() {
        if (this.currentCall && typeof this.currentCall.accept === 'function') {
            this.currentCall.accept();
        }
    }

    /**
     * Reject incoming call
     */
    reject() {
        if (this.currentCall && typeof this.currentCall.reject === 'function') {
            this.currentCall.reject();
            this.currentCall = null;
        }
    }

    /**
     * Toggle mute
     */
    toggleMute() {
        if (this.currentCall) {
            this.isMuted = !this.isMuted;
            this.currentCall.mute(this.isMuted);
            return this.isMuted;
        }
        return false;
    }

    /**
     * Send DTMF digit
     */
    sendDigit(digit) {
        if (this.currentCall) {
            this.currentCall.sendDigits(digit);
        }
    }

    /**
     * Start call timer
     */
    startTimer() {
        this.callStartTime = Date.now();
        this.updateTimerDisplay();
        this.callTimer = setInterval(() => this.updateTimerDisplay(), 1000);
    }

    /**
     * Stop call timer
     */
    stopTimer() {
        if (this.callTimer) {
            clearInterval(this.callTimer);
            this.callTimer = null;
        }
        this.callStartTime = null;
    }

    /**
     * Update timer display
     */
    updateTimerDisplay() {
        if (!this.callStartTime) return;

        const elapsed = Math.floor((Date.now() - this.callStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        document.dispatchEvent(new CustomEvent('phoneCallTimer', {
            detail: { elapsed, display }
        }));
    }

    /**
     * Get call duration in seconds
     */
    getCallDuration() {
        if (!this.callStartTime) return 0;
        return Math.floor((Date.now() - this.callStartTime) / 1000);
    }

    /**
     * Check if currently on a call
     */
    isOnCall() {
        return this.currentCall !== null;
    }

    /**
     * Destroy the client
     */
    destroy() {
        this.stopTimer();
        if (this.device) {
            this.device.disconnectAll();
            this.device.destroy();
        }
    }
}

// Format phone number for display
function formatPhoneNumber(number) {
    if (!number) return '';
    const cleaned = number.replace(/\D/g, '');
    if (cleaned.length === 10) {
        return `(${cleaned.slice(0,3)}) ${cleaned.slice(3,6)}-${cleaned.slice(6)}`;
    } else if (cleaned.length === 11 && cleaned.startsWith('1')) {
        return `+1 (${cleaned.slice(1,4)}) ${cleaned.slice(4,7)}-${cleaned.slice(7)}`;
    }
    return number;
}

// Export for use
window.PhoneClient = PhoneClient;
window.formatPhoneNumber = formatPhoneNumber;
