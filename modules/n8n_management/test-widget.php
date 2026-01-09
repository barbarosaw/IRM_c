<?php
/**
 * N8N Management Module - Widget Test Page
 * Debug console for chat widget development
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['n8n_management']);
$is_active = $stmt->fetchColumn();
if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

$page_title = "Widget Test Environment";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

define('AW_SYSTEM', true);
include '../../components/header.php';
include '../../components/sidebar.php';
?>

<style>
.test-controls {
    background: rgba(255,255,255,0.95);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}
.console-output {
    background: #1e1e1e;
    color: #d4d4d4;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 11px;
    line-height: 1.4;
    border-radius: 8px;
    padding: 15px;
    height: 400px;
    overflow-y: auto;
}
.console-output .log-time { color: #6a9955; }
.console-output .log-info { color: #4fc1ff; }
.console-output .log-error { color: #f14c4c; }
.console-output .log-warn { color: #cca700; }
.console-output .log-request { color: #ce9178; }
.console-output .log-response { color: #b5cea8; }
.console-output .log-context { color: #dcdcaa; }
.console-output .log-section {
    color: #569cd6;
    font-weight: bold;
    border-top: 1px solid #444;
    margin-top: 10px;
    padding-top: 10px;
}
.console-output pre {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
    color: inherit;
    background: transparent;
    font-size: inherit;
}
.lead-data-card, .brain-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    font-size: 12px;
}
.lead-data-card dt, .brain-card dt {
    font-weight: 600;
    color: #666;
    font-size: 10px;
    text-transform: uppercase;
}
.lead-data-card dd, .brain-card dd {
    margin-bottom: 8px;
    font-family: monospace;
    word-break: break-all;
    color: #333;
}
.lead-data-card dd.empty, .brain-card dd.empty { color: #999; font-style: italic; }

/* Brain State Styling */
.brain-state-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.state-greeting { background: #e3f2fd; color: #1565c0; }
.state-collecting_lead { background: #fff3e0; color: #e65100; }
.state-exploring_services { background: #e8f5e9; color: #2e7d32; }
.state-booking_flow { background: #fce4ec; color: #c2185b; }
.state-booking_confirmation { background: #f3e5f5; color: #7b1fa2; }
.state-completed { background: #e0e0e0; color: #424242; }

.engagement-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}
.engagement-low { background: #ffcdd2; color: #c62828; }
.engagement-medium { background: #fff9c4; color: #f9a825; }
.engagement-high { background: #c8e6c9; color: #2e7d32; }
.engagement-very_high { background: #b2dfdb; color: #00695c; }

.rule-tag {
    display: inline-block;
    background: #e3f2fd;
    color: #1565c0;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 9px;
    margin: 2px;
}
.action-tag {
    display: inline-block;
    background: #fff3e0;
    color: #e65100;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 9px;
    margin: 2px;
}
.topic-tag {
    display: inline-block;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 9px;
    margin: 2px;
}
.booking-info {
    background: #fce4ec;
    border-radius: 6px;
    padding: 8px;
    margin-top: 5px;
    font-size: 11px;
}
.booking-info .label { color: #880e4f; font-weight: 600; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-terminal me-2"></i>Widget Debug Console
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">N8N Management</a></li>
                        <li class="breadcrumb-item active">Test Widget</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Controls -->
            <div class="test-controls">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <button type="button" class="btn btn-danger" onclick="newSession()">
                            <i class="fas fa-plus me-1"></i> New Session
                        </button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-warning" onclick="clearAllData()">
                            <i class="fas fa-trash me-1"></i> Clear All Data
                        </button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-secondary" onclick="clearConsole()">
                            <i class="fas fa-eraser me-1"></i> Clear Console
                        </button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-info" onclick="refreshLeadData()">
                            <i class="fas fa-sync me-1"></i> Refresh Lead Data
                        </button>
                    </div>
                    <div class="col-auto ms-auto">
                        <span class="badge bg-success" id="status-badge">
                            <i class="fas fa-circle me-1"></i> Widget Active
                        </span>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Console Output -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h3 class="card-title"><i class="fas fa-terminal me-2"></i>API Console</h3>
                            <div class="card-tools">
                                <span class="badge bg-info" id="msg-count">0 messages</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="console-output" id="console-output">
                                <div><span class="log-time">[<?= date('H:i:s') ?>]</span> <span class="log-info">Debug console initialized. Chat widget loaded.</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Lead Data -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-tag me-2"></i>Lead Data</h3>
                        </div>
                        <div class="card-body">
                            <div class="lead-data-card" id="lead-data-container">
                                <div class="row">
                                    <div class="col-6">
                                        <dl class="mb-0">
                                            <dt>Session ID</dt>
                                            <dd id="lead-session" class="empty">-</dd>
                                            <dt>Full Name</dt>
                                            <dd id="lead-name" class="empty">-</dd>
                                            <dt>Email</dt>
                                            <dd id="lead-email" class="empty">-</dd>
                                            <dt>Phone</dt>
                                            <dd id="lead-phone" class="empty">-</dd>
                                            <dt>Company</dt>
                                            <dd id="lead-company" class="empty">-</dd>
                                            <dt>Industry</dt>
                                            <dd id="lead-industry" class="empty">-</dd>
                                        </dl>
                                    </div>
                                    <div class="col-6">
                                        <dl class="mb-0">
                                            <dt>Company Size</dt>
                                            <dd id="lead-size" class="empty">-</dd>
                                            <dt>Location</dt>
                                            <dd id="lead-location" class="empty">-</dd>
                                            <dt>Position Needed</dt>
                                            <dd id="lead-position" class="empty">-</dd>
                                            <dt>Primary Intent</dt>
                                            <dd id="lead-intent" class="empty">-</dd>
                                            <dt>Interests</dt>
                                            <dd id="lead-interests" class="empty">-</dd>
                                            <dt>Purchase Likelihood</dt>
                                            <dd id="lead-likelihood" class="empty">-</dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Brain State -->
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h3 class="card-title"><i class="fas fa-brain me-2"></i>Conversation Brain</h3>
                        </div>
                        <div class="card-body">
                            <div class="brain-card">
                                <dl class="mb-0">
                                    <dt>State</dt>
                                    <dd id="brain-state"><span class="brain-state-badge state-greeting">greeting</span></dd>

                                    <dt>Engagement</dt>
                                    <dd id="brain-engagement"><span class="engagement-badge engagement-medium">medium</span></dd>

                                    <dt>Turn Count</dt>
                                    <dd id="brain-turns">0</dd>

                                    <dt>Last Intent</dt>
                                    <dd id="brain-intent" class="empty">-</dd>

                                    <dt>Last AI Action</dt>
                                    <dd id="brain-action" class="empty">-</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Intent -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h3 class="card-title"><i class="fas fa-calendar-check me-2"></i>Booking Intent</h3>
                        </div>
                        <div class="card-body py-2">
                            <div id="booking-container">
                                <div class="text-muted small text-center py-2">No booking intent yet</div>
                            </div>
                        </div>
                    </div>

                    <!-- Topics Discussed -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h3 class="card-title"><i class="fas fa-comments me-2"></i>Topics Discussed</h3>
                        </div>
                        <div class="card-body py-2">
                            <div id="topics-container">
                                <div class="text-muted small text-center py-2">No topics yet</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rules & Actions -->
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title"><i class="fas fa-bolt me-2"></i>Active Rules</h3>
                        </div>
                        <div class="card-body py-2">
                            <div id="rules-container">
                                <div class="text-muted small text-center py-2">No rules triggered</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Actions -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h3 class="card-title"><i class="fas fa-tasks me-2"></i>Pending Actions</h3>
                        </div>
                        <div class="card-body py-2">
                            <div id="actions-container">
                                <div class="text-muted small text-center py-2">No pending actions</div>
                            </div>
                        </div>
                    </div>

                    <!-- Test Scenarios -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-vial me-2"></i>Quick Tests</h3>
                        </div>
                        <div class="list-group list-group-flush">
                            <button class="list-group-item list-group-item-action py-2" onclick="testScenario('booking')">
                                <i class="fas fa-calendar text-primary me-2"></i> Booking Flow
                            </button>
                            <button class="list-group-item list-group-item-action py-2" onclick="testScenario('services')">
                                <i class="fas fa-briefcase text-success me-2"></i> Services Inquiry
                            </button>
                            <button class="list-group-item list-group-item-action py-2" onclick="testScenario('name')">
                                <i class="fas fa-user text-info me-2"></i> Give Name
                            </button>
                            <button class="list-group-item list-group-item-action py-2" onclick="testScenario('time')">
                                <i class="fas fa-clock text-warning me-2"></i> Give Time: 3pm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Test environment configuration
window.AW_CHAT_TEST_MODE = true;
window.AW_CHAT_DOMAIN_BYPASS = true;

let messageCount = 0;

function logToConsole(type, ...args) {
    const output = document.getElementById('console-output');
    if (!output) return;

    const time = new Date().toLocaleTimeString();
    const message = args.map(arg => {
        if (typeof arg === 'object') {
            try {
                return JSON.stringify(arg, null, 2);
            } catch(e) {
                return String(arg);
            }
        }
        return String(arg);
    }).join(' ');

    const typeClass = {
        'log': 'log-info',
        'error': 'log-error',
        'warn': 'log-warn',
        'request': 'log-request',
        'response': 'log-response',
        'context': 'log-context',
        'section': 'log-section'
    }[type] || 'log-info';

    output.innerHTML += `<div><span class="log-time">[${time}]</span> <span class="${typeClass}"><pre>${escapeHtml(message)}</pre></span></div>`;
    output.scrollTop = output.scrollHeight;
}

// Override fetch to log all API calls
const originalFetch = window.fetch;
window.fetch = async function(...args) {
    const url = args[0];
    const options = args[1] || {};

    // Only log our API calls
    if (typeof url === 'string' && (url.includes('n8n') || url.includes('chat'))) {
        const shortUrl = url.split('/').slice(-2).join('/');

        logToConsole('section', `=== API CALL: ${shortUrl} ===`);

        if (options.body) {
            try {
                const body = JSON.parse(options.body);
                logToConsole('request', 'REQUEST BODY:\n' + JSON.stringify(body, null, 2));
            } catch(e) {
                logToConsole('request', 'REQUEST BODY: ' + options.body);
            }
        }

        try {
            const response = await originalFetch.apply(this, args);
            const clone = response.clone();

            try {
                const data = await clone.json();
                logToConsole('response', 'RESPONSE:\n' + JSON.stringify(data, null, 2));

                // If this is a chat response, update message count
                if (data.response) {
                    messageCount++;
                    document.getElementById('msg-count').textContent = messageCount + ' messages';
                }

                // If lead data updated, refresh display
                if (data.lead_update || data.lead_data) {
                    setTimeout(refreshLeadData, 100);
                }
            } catch(e) {
                const text = await clone.text();
                logToConsole('response', 'RESPONSE (text): ' + text.substring(0, 500));
            }

            return response;
        } catch(e) {
            logToConsole('error', 'FETCH ERROR: ' + e.message);
            throw e;
        }
    }

    return originalFetch.apply(this, args);
};

// Console overrides
const originalConsoleLog = console.log;
const originalConsoleError = console.error;
const originalConsoleWarn = console.warn;

console.log = function(...args) {
    originalConsoleLog.apply(console, args);
    // Only log AW Chat related messages
    const str = args.join(' ');
    if (str.includes('AW') || str.includes('Chat') || str.includes('session')) {
        logToConsole('log', ...args);
    }
};

console.error = function(...args) {
    originalConsoleError.apply(console, args);
    logToConsole('error', ...args);
};

console.warn = function(...args) {
    originalConsoleWarn.apply(console, args);
    logToConsole('warn', ...args);
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function newSession() {
    if (confirm('Start a new session? This will clear current session data.')) {
        if (window.AWChat && typeof window.AWChat.newSession === 'function') {
            window.AWChat.newSession();
            logToConsole('section', '=== NEW SESSION STARTED ===');
        } else {
            localStorage.removeItem('aw_chat_session');
            localStorage.removeItem('aw_chat_messages');
            const widget = document.getElementById('aw-chat-widget');
            if (widget) widget.remove();
            logToConsole('section', '=== SESSION CLEARED MANUALLY ===');
            loadWidget();
        }
        messageCount = 0;
        document.getElementById('msg-count').textContent = '0 messages';
        clearLeadDisplay();
        clearBrainDisplay();
        setTimeout(() => {
            refreshLeadData();
            refreshBrainData();
        }, 500);
    }
}

function clearAllData() {
    if (confirm('Clear ALL data including visitor ID?')) {
        localStorage.removeItem('aw_chat_visitor');
        localStorage.removeItem('aw_chat_session');
        localStorage.removeItem('aw_chat_messages');
        const widget = document.getElementById('aw-chat-widget');
        if (widget) widget.remove();
        window.AWChat = null;
        logToConsole('section', '=== ALL DATA CLEARED ===');
        loadWidget();
        messageCount = 0;
        document.getElementById('msg-count').textContent = '0 messages';
        clearLeadDisplay();
        clearBrainDisplay();
    }
}

function clearConsole() {
    document.getElementById('console-output').innerHTML =
        '<div><span class="log-time">[' + new Date().toLocaleTimeString() + ']</span> <span class="log-info">Console cleared</span></div>';
}

function clearLeadDisplay() {
    const fields = ['session', 'name', 'email', 'phone', 'company', 'industry', 'size', 'location', 'position', 'intent', 'interests', 'likelihood', 'qa'];
    fields.forEach(f => {
        const el = document.getElementById('lead-' + f);
        if (el) {
            el.textContent = '-';
            el.className = 'empty';
        }
    });
}

async function refreshLeadData() {
    const sessionId = localStorage.getItem('aw_chat_session');
    if (!sessionId) {
        clearLeadDisplay();
        return;
    }

    document.getElementById('lead-session').textContent = sessionId.substring(0, 20) + '...';
    document.getElementById('lead-session').className = '';

    try {
        const response = await originalFetch('https://irm.abroadworks.com/modules/n8n_management/api/chat/context.php?session_id=' + sessionId, {
            headers: { 'X-Chat-API-Key': 'd24a784168becfada7644485c4aca234803b9c4ced3fcdee48e712092e5b9373' }
        });
        const data = await response.json();

        if (data.success && data.lead_data) {
            const lead = data.lead_data;
            setLeadField('name', lead.full_name);
            setLeadField('email', lead.email);
            setLeadField('phone', lead.phone);
            setLeadField('company', lead.business_name);
            setLeadField('industry', lead.industry);
            setLeadField('size', lead.company_size);
            setLeadField('location', lead.location);
            setLeadField('position', lead.position_needed);
            setLeadField('intent', lead.primary_intent);
            setLeadField('likelihood', lead.purchase_likelihood);

            if (data.interests && data.interests.length > 0) {
                setLeadField('interests', data.interests.join(', '));
            } else {
                setLeadField('interests', null);
            }

            if (data.qa_history && data.qa_history.length > 0) {
                const qaText = data.qa_history.map(qa => `Q: ${qa.q}\nA: ${qa.a}`).join('\n\n');
                setLeadField('qa', qaText);
            } else {
                setLeadField('qa', null);
            }
        }
    } catch(e) {
        console.error('Failed to fetch lead data:', e);
    }
}

function setLeadField(field, value) {
    const el = document.getElementById('lead-' + field);
    if (el) {
        if (value) {
            el.textContent = value;
            el.className = '';
        } else {
            el.textContent = '-';
            el.className = 'empty';
        }
    }
}

function testScenario(scenario) {
    const messages = {
        booking: "I'd like to book a consultation call",
        services: "Tell me about your virtual assistant services",
        name: "My name is John Doe",
        email: "My email is test@example.com",
        time: "Let's do 3pm tomorrow"
    };

    const input = document.querySelector('#aw-chat-input');
    if (input) {
        input.value = messages[scenario];
        input.focus();
        logToConsole('log', 'Test scenario loaded: ' + scenario);
    } else {
        logToConsole('warn', 'Widget input not found. Open the chat first.');
    }
}

// Listen for debug events from widget
window.addEventListener('awchat:debug', function(e) {
    const debug = e.detail;
    updateDebugPanels(debug);
    logToConsole('section', '=== ORCHESTRATOR DEBUG ===');
    logToConsole('context', JSON.stringify(debug, null, 2));
});

function updateDebugPanels(debug) {
    if (!debug) return;

    // Update state
    if (debug.state) {
        const state = debug.state.current || 'greeting';
        document.getElementById('brain-state').innerHTML =
            `<span class="brain-state-badge state-${state}">${state.replace(/_/g, ' ')}</span>`;

        // Show state action
        if (debug.state.action) {
            document.getElementById('brain-action').textContent = debug.state.action;
            document.getElementById('brain-action').className = '';
        }
    }

    // Update intent
    if (debug.intent) {
        document.getElementById('brain-intent').textContent =
            `${debug.intent.current} (${Math.round(debug.intent.confidence * 100)}%)`;
        document.getElementById('brain-intent').className = '';
    }

    // Update context summary
    if (debug.context_sent) {
        const ctx = debug.context_sent;

        // Turn count
        if (ctx.summary) {
            document.getElementById('brain-turns').textContent = ctx.summary.turn || 0;

            // Engagement from lead score
            const score = ctx.summary.lead_score || 0;
            let engagement = 'low';
            if (score > 70) engagement = 'very_high';
            else if (score > 50) engagement = 'high';
            else if (score > 30) engagement = 'medium';

            document.getElementById('brain-engagement').innerHTML =
                `<span class="engagement-badge engagement-${engagement}">${engagement.replace('_', ' ')} (${score})</span>`;
        }

        // Instructions as "rules"
        if (ctx.instructions && ctx.instructions.length > 0) {
            const rulesHtml = ctx.instructions.slice(0, 5).map(inst =>
                `<span class="rule-tag">${inst.substring(0, 40)}${inst.length > 40 ? '...' : ''}</span>`
            ).join('');
            document.getElementById('rules-container').innerHTML = rulesHtml;
        }

        // Missing fields as "actions"
        if (ctx.missing && ctx.missing.length > 0) {
            const actionsHtml = ctx.missing.map(field =>
                `<span class="action-tag">collect: ${field}</span>`
            ).join('');
            document.getElementById('actions-container').innerHTML = actionsHtml;
        } else {
            document.getElementById('actions-container').innerHTML =
                '<span class="action-tag" style="background:#c8e6c9;color:#2e7d32;">All required data collected!</span>';
        }

        // Booking info
        if (ctx.booking && Object.keys(ctx.booking).length > 0) {
            let html = '<div class="booking-info">';
            for (const [key, value] of Object.entries(ctx.booking)) {
                html += `<div><span class="label">${key}:</span> ${value}</div>`;
            }
            html += '</div>';
            document.getElementById('booking-container').innerHTML = html;
        }
    }

    // Update validation
    if (debug.validation) {
        if (!debug.validation.valid && debug.validation.warnings) {
            logToConsole('warn', 'VALIDATION WARNINGS: ' + debug.validation.warnings.join(', '));
        }
    }
}

async function refreshBrainData() {
    const sessionId = localStorage.getItem('aw_chat_session');
    if (!sessionId) {
        clearBrainDisplay();
        return;
    }

    // Also fetch brain status for persistent data
    try {
        const response = await originalFetch('https://irm.abroadworks.com/modules/n8n_management/api/chat/brain-status.php?session_id=' + sessionId, {
            headers: { 'X-Chat-API-Key': 'd24a784168becfada7644485c4aca234803b9c4ced3fcdee48e712092e5b9373' }
        });
        const data = await response.json();

        if (data.success && data.brain) {
            const brain = data.brain;

            // Update state if not updated by debug event
            const state = brain.state || 'greeting';
            document.getElementById('brain-state').innerHTML =
                `<span class="brain-state-badge state-${state}">${state.replace(/_/g, ' ')}</span>`;

            document.getElementById('brain-turns').textContent = brain.turn_count || 0;

            if (brain.last_user_intent) {
                document.getElementById('brain-intent').textContent = brain.last_user_intent;
                document.getElementById('brain-intent').className = '';
            }

            if (brain.last_ai_action) {
                document.getElementById('brain-action').textContent = brain.last_ai_action;
                document.getElementById('brain-action').className = '';
            }

            // Topics
            if (brain.topics_discussed) {
                try {
                    const topics = typeof brain.topics_discussed === 'string'
                        ? JSON.parse(brain.topics_discussed)
                        : brain.topics_discussed;

                    if (Object.keys(topics).length > 0) {
                        let html = '';
                        for (const [topic, data] of Object.entries(topics)) {
                            html += `<span class="topic-tag">${topic.replace(/_/g, ' ')}</span>`;
                        }
                        document.getElementById('topics-container').innerHTML = html;
                    }
                } catch(e) {}
            }
        }
    } catch(e) {
        console.error('Failed to fetch brain data:', e);
    }
}

function updateBrainDetails(brain) {
    // Last intent
    if (brain.last_user_intent) {
        document.getElementById('brain-intent').textContent = brain.last_user_intent;
        document.getElementById('brain-intent').className = '';
    }

    // Last AI action
    if (brain.last_ai_action) {
        document.getElementById('brain-action').textContent = brain.last_ai_action;
        document.getElementById('brain-action').className = '';
    }

    // Booking intent
    if (brain.booking_intent) {
        try {
            const booking = typeof brain.booking_intent === 'string'
                ? JSON.parse(brain.booking_intent)
                : brain.booking_intent;

            if (Object.keys(booking).length > 0) {
                let html = '<div class="booking-info">';
                if (booking.requested_date) html += `<div><span class="label">Date:</span> ${booking.requested_date}</div>`;
                if (booking.requested_time) html += `<div><span class="label">Time:</span> ${booking.requested_time}</div>`;
                if (booking.timezone) html += `<div><span class="label">TZ:</span> ${booking.timezone}</div>`;
                if (booking.status) html += `<div><span class="label">Status:</span> ${booking.status}</div>`;
                html += '</div>';
                document.getElementById('booking-container').innerHTML = html;
            } else {
                document.getElementById('booking-container').innerHTML =
                    '<div class="text-muted small text-center py-2">No booking intent yet</div>';
            }
        } catch(e) {
            document.getElementById('booking-container').innerHTML =
                '<div class="text-muted small text-center py-2">No booking intent yet</div>';
        }
    }

    // Topics discussed
    if (brain.topics_discussed) {
        try {
            const topics = typeof brain.topics_discussed === 'string'
                ? JSON.parse(brain.topics_discussed)
                : brain.topics_discussed;

            if (Object.keys(topics).length > 0) {
                let html = '';
                for (const [topic, data] of Object.entries(topics)) {
                    let details = [];
                    if (data.service_explained) details.push('explained');
                    if (data.pricing_shared) details.push('pricing');
                    if (data.mentioned) details.push('mentioned');

                    html += `<span class="topic-tag">${topic.replace('_', ' ')}`;
                    if (details.length > 0) html += ` (${details.join(', ')})`;
                    html += '</span>';
                }
                document.getElementById('topics-container').innerHTML = html;
            } else {
                document.getElementById('topics-container').innerHTML =
                    '<div class="text-muted small text-center py-2">No topics yet</div>';
            }
        } catch(e) {
            document.getElementById('topics-container').innerHTML =
                '<div class="text-muted small text-center py-2">No topics yet</div>';
        }
    }
}

function clearBrainDisplay() {
    document.getElementById('brain-state').innerHTML =
        '<span class="brain-state-badge state-greeting">greeting</span>';
    document.getElementById('brain-engagement').innerHTML =
        '<span class="engagement-badge engagement-medium">medium</span>';
    document.getElementById('brain-turns').textContent = '0';
    document.getElementById('brain-intent').textContent = '-';
    document.getElementById('brain-intent').className = 'empty';
    document.getElementById('brain-action').textContent = '-';
    document.getElementById('brain-action').className = 'empty';
    document.getElementById('booking-container').innerHTML =
        '<div class="text-muted small text-center py-2">No booking intent yet</div>';
    document.getElementById('topics-container').innerHTML =
        '<div class="text-muted small text-center py-2">No topics yet</div>';
    document.getElementById('rules-container').innerHTML =
        '<div class="text-muted small text-center py-2">No rules triggered</div>';
    document.getElementById('actions-container').innerHTML =
        '<div class="text-muted small text-center py-2">No pending actions</div>';
}

function loadWidget() {
    const script = document.createElement('script');
    script.src = 'widget/abroadworks-chat.js?t=' + Date.now();
    document.body.appendChild(script);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadWidget();
    setTimeout(() => {
        refreshLeadData();
        refreshBrainData();
    }, 1000);
    setInterval(() => {
        refreshLeadData();
        refreshBrainData();
    }, 5000);
});
</script>

<?php include '../../components/footer.php'; ?>
