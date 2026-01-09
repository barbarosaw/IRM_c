/**
 * AbroadWorks Chat Widget v4
 * With orchestrator integration and full debug support
 */
(function() {
    'use strict';

    const CONFIG_URL = 'https://irm.abroadworks.com/modules/n8n_management/api/widget/config.php';
    const ORCHESTRATOR_URL = 'https://irm.abroadworks.com/modules/n8n_management/api/chat/orchestrator.php';
    const CHECK_SESSION_URL = 'https://irm.abroadworks.com/modules/n8n_management/api/chat/check-session.php';
    const END_SESSION_URL = 'https://irm.abroadworks.com/modules/n8n_management/api/chat/end-session.php';
    const STORAGE_KEY = 'aw_chat_visitor';
    const SESSION_KEY = 'aw_chat_session';
    const MESSAGES_KEY = 'aw_chat_messages';
    const API_KEY = 'd24a784168becfada7644485c4aca234803b9c4ced3fcdee48e712092e5b9373';
    const ALLOWED_DOMAINS = ['abroadworks.com', 'www.abroadworks.com', 'irm.abroadworks.com', 'localhost'];

    let config = { enabled: true, title: 'Hi there! 👋', subtitle: 'We typically reply instantly', primaryColor: '#e74266', position: 'bottom-right', triggerTime: 30000, triggerScroll: 50, webhookUrl: '' };
    let lastDebugData = null; // Store debug data for UI panel
    let isOpen = false, isLoading = false, messages = [], visitorId = null, sessionId = null, hasActiveSession = false;
    let sessionInfo = { isJobSeeker: false, collectedInfo: null, offTopicAttempts: 0 };

    // Domain check
    function isDomainAllowed() {
        if (window.AW_CHAT_DOMAIN_BYPASS) return true;
        const host = window.location.hostname;
        return ALLOWED_DOMAINS.some(d => host === d || host.endsWith('.' + d));
    }

    function getVisitorId() {
        let stored = localStorage.getItem(STORAGE_KEY);
        if (stored) { try { return JSON.parse(stored).visitorId; } catch(e){} }
        const newId = 'vis_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ visitorId: newId, created: Date.now() }));
        return newId;
    }

    async function loadConfig() {
        try {
            const r = await fetch(CONFIG_URL);
            const d = await r.json();
            if (d.success && d.config) config = { ...config, ...d.config };
        } catch(e) { console.warn('AW Chat: Config error', e); }
    }

    async function checkExistingSession() {
        try {
            const r = await fetch(CHECK_SESSION_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ visitor_id: visitorId })
            });
            const d = await r.json();
            if (d.has_active_session) {
                hasActiveSession = true;
                sessionId = d.session_id;
                localStorage.setItem(SESSION_KEY, sessionId);
                messages = d.messages || [];
                // Store session metadata
                sessionInfo = {
                    isJobSeeker: d.is_job_seeker || false,
                    collectedInfo: d.collected_info || null,
                    offTopicAttempts: d.off_topic_attempts || 0,
                    primaryIntent: d.primary_intent || null
                };
                // Cache messages locally for faster reload
                localStorage.setItem(MESSAGES_KEY, JSON.stringify(messages));
                return true;
            }
        } catch(e) {
            console.warn('AW Chat: Session check error', e);
            // Try to load cached messages if server unavailable
            const cached = localStorage.getItem(MESSAGES_KEY);
            if (cached) {
                try {
                    messages = JSON.parse(cached);
                    sessionId = localStorage.getItem(SESSION_KEY);
                    if (messages.length > 0) {
                        hasActiveSession = true;
                        return true;
                    }
                } catch(e2) {}
            }
        }
        return false;
    }

    // Start a new session (clear existing)
    async function startNewSession() {
        // End current session if exists
        if (sessionId) {
            try {
                await fetch(END_SESSION_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: sessionId, visitor_id: visitorId })
                });
            } catch(e) { console.warn('AW Chat: End session error', e); }
        }
        // Clear local storage
        localStorage.removeItem(SESSION_KEY);
        localStorage.removeItem(MESSAGES_KEY);
        sessionId = null;
        messages = [];
        hasActiveSession = false;
        sessionInfo = { isJobSeeker: false, collectedInfo: null, offTopicAttempts: 0 };
        // Re-render
        const container = document.getElementById('aw-chat-messages');
        if (container) {
            container.innerHTML = '';
            addMessageToUI("Hello! How can I help you today?", false);
            showIntentButtons();
        }
        console.log('AW Chat: New session started');
    }

    // Expose for external use (test page)
    window.AWChat = {
        newSession: startNewSession,
        getSessionInfo: () => ({ visitorId, sessionId, hasActiveSession, messages: messages.length, sessionInfo }),
        getDebugData: () => lastDebugData,
        open: () => { if (!isOpen) toggleChat(); },
        close: () => { if (isOpen) toggleChat(); }
    };

    function createWidget() {
        const pos = config.position === 'bottom-left' ? 'left: 20px;' : 'right: 20px;';
        const html = `<div id="aw-chat-widget" style="position:fixed;bottom:20px;${pos}z-index:999999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <div id="aw-chat-toggle" style="width:60px;height:60px;border-radius:50%;background:${config.primaryColor};cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                <svg id="aw-chat-icon" width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>
                <svg id="aw-close-icon" width="28" height="28" viewBox="0 0 24 24" fill="white" style="display:none;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </div>
            <div id="aw-chat-window" style="display:none;position:absolute;bottom:75px;${config.position==='bottom-left'?'left:0;':'right:0;'}width:380px;max-width:calc(100vw - 40px);height:550px;max-height:calc(100vh - 120px);background:white;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,0.12);overflow:hidden;flex-direction:column;">
                <div style="background:${config.primaryColor};color:white;padding:20px;"><h3 style="margin:0 0 4px 0;font-size:18px;">${config.title}</h3><p style="margin:0;font-size:13px;opacity:0.9;">${config.subtitle}</p></div>
                <div id="aw-chat-messages" style="flex:1;overflow-y:auto;padding:16px;background:#f9fafb;"></div>
                <div style="padding:12px 16px;border-top:1px solid #e5e7eb;background:white;"><div style="display:flex;gap:8px;">
                    <input type="text" id="aw-chat-input" placeholder="Type your message..." style="flex:1;padding:12px 16px;border:1px solid #e5e7eb;border-radius:24px;font-size:14px;outline:none;">
                    <button id="aw-send-btn" style="width:44px;height:44px;border-radius:50%;background:${config.primaryColor};border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div></div>
            </div>
        </div>`;
        const c = document.createElement('div'); c.innerHTML = html;
        document.body.appendChild(c.firstElementChild);
        addStyles();
        setupEvents();
        renderInitialMessages();
    }

    function renderInitialMessages() {
        const container = document.getElementById('aw-chat-messages');
        if (hasActiveSession && messages.length > 0) {
            messages.forEach(m => addMessageToUI(m.content, m.role === 'user'));
        } else {
            addMessageToUI("Hello! How can I help you today?", false);
            showIntentButtons();
        }
    }

    function showIntentButtons() {
        const container = document.getElementById('aw-chat-messages');
        const btns = document.createElement('div');
        btns.id = 'aw-intent-buttons';
        btns.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;';
        const intents = [['services','Our Services'],['booking','Book a Call'],['careers','Careers'],['general','Other']];
        intents.forEach(([id,label]) => {
            const b = document.createElement('button');
            b.dataset.intent = id;
            b.textContent = label;
            b.style.cssText = `background:${config.primaryColor}15;color:${config.primaryColor};border:1px solid ${config.primaryColor}40;padding:8px 16px;border-radius:20px;font-size:13px;cursor:pointer;`;
            b.onclick = () => { document.getElementById('aw-chat-input').value = label; sendMessage(id); };
            btns.appendChild(b);
        });
        container.appendChild(btns);
    }

    function addMessageToUI(content, isUser) {
        const container = document.getElementById('aw-chat-messages');
        const div = document.createElement('div');
        div.style.cssText = `display:flex;margin-bottom:12px;${isUser?'justify-content:flex-end;':''}`;
        const style = isUser ? `background:${config.primaryColor};color:white;` : `background:white;box-shadow:0 1px 2px rgba(0,0,0,0.05);`;
        div.innerHTML = `<div style="${style}padding:12px 16px;border-radius:12px;max-width:80%;"><p style="margin:0;font-size:14px;line-height:1.5;white-space:pre-wrap;">${escapeHtml(content)}</p></div>`;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
        const btns = document.getElementById('aw-intent-buttons');
        if (btns) btns.remove();
    }

    function showTyping() {
        const c = document.getElementById('aw-chat-messages');
        const d = document.createElement('div'); d.id = 'aw-typing';
        d.innerHTML = `<div style="background:white;padding:12px 16px;border-radius:12px;"><div style="display:flex;gap:4px;"><span class="aw-dot"></span><span class="aw-dot"></span><span class="aw-dot"></span></div></div>`;
        c.appendChild(d); c.scrollTop = c.scrollHeight;
    }
    function hideTyping() { const t = document.getElementById('aw-typing'); if(t) t.remove(); }

    function showErrorRecovery() {
        const container = document.getElementById('aw-chat-messages');
        const div = document.createElement('div');
        div.id = 'aw-error-recovery';
        div.style.cssText = 'margin-bottom:12px;';
        div.innerHTML = `
            <div style="background:#fef2f2;border:1px solid #fecaca;padding:16px;border-radius:12px;">
                <p style="margin:0 0 12px 0;font-size:14px;color:#991b1b;">
                    I apologize, but I encountered an issue processing your request.
                </p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button id="aw-retry-btn" style="background:${config.primaryColor};color:white;border:none;padding:8px 16px;border-radius:20px;font-size:13px;cursor:pointer;">
                        Try Again
                    </button>
                    <button id="aw-new-session-btn" style="background:white;color:${config.primaryColor};border:1px solid ${config.primaryColor};padding:8px 16px;border-radius:20px;font-size:13px;cursor:pointer;">
                        Start New Chat
                    </button>
                    <button id="aw-refresh-btn" style="background:#f3f4f6;color:#374151;border:1px solid #d1d5db;padding:8px 16px;border-radius:20px;font-size:13px;cursor:pointer;">
                        Refresh Page
                    </button>
                </div>
            </div>
        `;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;

        // Event handlers
        document.getElementById('aw-retry-btn').onclick = () => {
            div.remove();
            // Get last user message and retry
            const lastUserMsg = messages.filter(m => m.role === 'user').pop();
            if (lastUserMsg) {
                document.getElementById('aw-chat-input').value = lastUserMsg.content;
                // Remove last user message from UI and array
                const msgElements = container.querySelectorAll('div[style*="justify-content:flex-end"]');
                if (msgElements.length > 0) msgElements[msgElements.length - 1].remove();
                messages = messages.slice(0, -1);
                sendMessage();
            }
        };
        document.getElementById('aw-new-session-btn').onclick = () => {
            div.remove();
            startNewSession();
        };
        document.getElementById('aw-refresh-btn').onclick = () => {
            window.location.reload();
        };
    }

    async function sendMessage(intent = null) {
        const input = document.getElementById('aw-chat-input');
        const msg = input.value.trim();
        if (!msg || isLoading) return;

        // Generate session ID if not exists
        if (!sessionId) {
            sessionId = 'ses_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
            localStorage.setItem(SESSION_KEY, sessionId);
        }

        isLoading = true; input.value = ''; input.disabled = true;
        addMessageToUI(msg, true);
        messages.push({ role: 'user', content: msg, timestamp: Date.now() });
        showTyping();

        try {
            const r = await fetch(ORCHESTRATOR_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Chat-API-Key': API_KEY
                },
                body: JSON.stringify({
                    visitor_id: visitorId,
                    session_id: sessionId,
                    message: msg
                })
            });
            const d = await r.json();
            hideTyping();

            // Store debug data for UI panel
            if (d.debug) {
                lastDebugData = d.debug;
                // Emit custom event for debug panel
                window.dispatchEvent(new CustomEvent('awchat:debug', { detail: d.debug }));
            }

            if (d.response) {
                addMessageToUI(d.response, false);
                messages.push({ role: 'assistant', content: d.response, timestamp: Date.now() });
            } else if (d.error) {
                // Don't show raw errors - show friendly message with recovery options
                console.error('AW Chat: API error', d.error);
                showErrorRecovery();
            }

            // Cache messages locally
            localStorage.setItem(MESSAGES_KEY, JSON.stringify(messages));
        } catch(e) {
            hideTyping();
            console.error('AW Chat: Send error', e);
            showErrorRecovery();
        }
        isLoading = false; input.disabled = false; input.focus();
    }

    function detectIntent(msg) {
        const l = msg.toLowerCase();
        if (/service|virtual|assistant|staff/.test(l)) return 'services';
        if (/book|call|meeting|consult/.test(l)) return 'booking';
        if (/job|career|work|hire|apply/.test(l)) return 'careers';
        return 'general';
    }

    function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    function toggleChat() {
        isOpen = !isOpen;
        const w = document.getElementById('aw-chat-window');
        const ci = document.getElementById('aw-chat-icon');
        const xi = document.getElementById('aw-close-icon');
        w.style.display = isOpen ? 'flex' : 'none';
        ci.style.display = isOpen ? 'none' : 'block';
        xi.style.display = isOpen ? 'block' : 'none';
        if (isOpen) document.getElementById('aw-chat-input').focus();
    }

    function setupEvents() {
        document.getElementById('aw-chat-toggle').onclick = toggleChat;
        document.getElementById('aw-send-btn').onclick = () => sendMessage();
        document.getElementById('aw-chat-input').onkeypress = e => { if (e.key === 'Enter') sendMessage(); };
    }

    function addStyles() {
        const s = document.createElement('style');
        s.textContent = `@keyframes aw-bounce{0%,80%,100%{transform:scale(0)}40%{transform:scale(1)}}.aw-dot{width:8px;height:8px;background:#94a3b8;border-radius:50%;animation:aw-bounce 1.4s infinite}#aw-chat-messages::-webkit-scrollbar{width:6px}#aw-chat-messages::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}`;
        document.head.appendChild(s);
    }

    function setupTriggers() {
        if (config.triggerTime > 0) setTimeout(() => { if (!isOpen) toggleChat(); }, config.triggerTime);
        if (config.triggerScroll > 0) {
            let triggered = false;
            window.onscroll = () => { if (triggered || isOpen) return; const p = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100; if (p >= config.triggerScroll) { triggered = true; toggleChat(); } };
        }
    }

    async function init() {
        if (document.getElementById('aw-chat-widget')) return;
        if (!isDomainAllowed()) { console.log('AW Chat: Domain not allowed'); return; }
        visitorId = getVisitorId();
        sessionId = localStorage.getItem(SESSION_KEY);
        await loadConfig();
        if (!config.enabled && !window.AW_CHAT_TEST_MODE) { console.log('AW Chat: Disabled'); return; }
        await checkExistingSession();
        createWidget();
        setupTriggers();
        console.log('AW Chat: Ready');
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
