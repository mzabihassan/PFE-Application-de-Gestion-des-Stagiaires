{{-- ══════════════════════════════════════════════════════════════════════
     AI Chat Popup — visible to all authenticated users
══════════════════════════════════════════════════════════════════════ --}}
<div id="ai-chat-fab" title="Assistant IA">
    <span class="ai-chat-fab-icon"><i class="bi bi-stars"></i></span>
    <span class="ai-chat-fab-badge" id="chatUnreadBadge" style="display:none"></span>
</div>

<div id="ai-chat-panel" class="ai-chat-hidden">
    <div class="ai-chat-header">
        <div class="d-flex align-items-center gap-2">
            <span style="font-size:1.1rem"><i class="bi bi-stars"></i></span>
            <div>
                <div class="fw-bold" style="font-size:.88rem;line-height:1.2">Assistant IA</div>
                <div style="font-size:.7rem;opacity:.75">Posez vos questions</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1">
            <button class="ai-chat-header-btn" id="chatHistoryBtn" title="Historique des conversations">
                <i class="bi bi-list"></i>
            </button>
            <button class="ai-chat-header-btn" id="chatClearBtn" title="Nouvelle conversation">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button class="ai-chat-header-btn" id="chatCloseBtn" title="Fermer">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    <div id="ai-chat-history-overlay" class="ai-chat-hidden" style="position:absolute;top:3.5rem;left:0;width:100%;height:calc(100% - 3.5rem);background:#fff;z-index:10;display:flex;flex-direction:column;transition:transform 0.2s;transform:translateX(100%);">
        <div style="padding:0.75rem 1rem;background:var(--surface-2);border-bottom:1px solid var(--hairline);display:flex;justify-content:space-between;align-items:center;">
            <h6 style="margin:0;font-size:0.85rem;color:var(--ink);font-weight:600;">Conversations récentes</h6>
            <button id="chatHistoryCloseBtn" style="background:none;border:none;color:var(--muted);cursor:pointer;"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="chatHistoryList" style="flex:1;overflow-y:auto;padding:0.5rem 0;">
            <div style="padding:1rem;text-align:center;font-size:0.8rem;color:var(--muted);">Chargement...</div>
        </div>
    </div>

    <div class="ai-chat-model-bar">
        <label for="chatModelSelect" style="font-size:.72rem;font-weight:600;color:var(--muted);white-space:nowrap">Modèle :</label>
        <select id="chatModelSelect" class="ai-chat-model-select">
            <option value="">Chargement…</option>
        </select>
    </div>

    <div class="ai-chat-messages" id="chatMessages">
        <div class="ai-chat-msg ai-chat-msg-bot">
            <div class="ai-chat-msg-content">
                👋 Bonjour <strong>{{ auth()->user()->full_name }}</strong> ! Je suis votre assistant IA.<br>
                Posez-moi des questions sur vos stagiaires, stages, tâches, absences…
            </div>
        </div>
    </div>

    <div class="ai-chat-input-bar">
        <textarea id="chatInput" class="ai-chat-input" rows="1"
                  placeholder="Écrire un message…" maxlength="2000"></textarea>
        <button id="chatSendBtn" class="ai-chat-send-btn" title="Envoyer" disabled>
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>

<style>
    #ai-chat-fab {
        position: fixed; bottom: 1.5rem; right: 1.5rem;
        width: 3.5rem; height: 3.5rem; border-radius: 50%;
        background: linear-gradient(135deg, var(--accent), var(--accent-deep));
        color: #fff; display: flex; align-items: center; justify-content: center;
        cursor: pointer; box-shadow: 0 10px 26px -8px rgba(0,184,163,.55);
        z-index: 9999; transition: transform .2s, box-shadow .2s;
    }
    #ai-chat-fab:hover { transform: scale(1.08); box-shadow: 0 14px 34px -8px rgba(0,184,163,.6); }
    .ai-chat-fab-icon { font-size: 1.45rem; }
    .ai-chat-fab-badge { position: absolute; top: -.2rem; right: -.2rem; width: 1rem; height: 1rem; border-radius: 50%; background: var(--danger); border: 2px solid #fff; }

    #ai-chat-panel {
        position: fixed; bottom: 5.5rem; right: 1.5rem;
        width: min(420px, calc(100vw - 2rem)); height: min(580px, calc(100vh - 8rem));
        border-radius: var(--r-lg); background: #fff; border: 1px solid var(--hairline);
        box-shadow: var(--sh-lg); z-index: 9998; display: flex; flex-direction: column;
        overflow: hidden; transition: opacity .22s, transform .22s;
    }
    #ai-chat-panel.ai-chat-hidden { opacity: 0; transform: translateY(20px) scale(.96); pointer-events: none; }

    .ai-chat-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: .7rem .9rem; background: linear-gradient(120deg, var(--ink), var(--accent-deep));
        color: #fff; flex-shrink: 0;
    }
    .ai-chat-header-btn {
        background: rgba(255,255,255,.15); border: none; color: #fff;
        width: 1.8rem; height: 1.8rem; border-radius: .4rem;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: .85rem; transition: background .15s;
    }
    .ai-chat-header-btn:hover { background: rgba(255,255,255,.28); }

    .ai-chat-model-bar { display: flex; align-items: center; gap: .45rem; padding: .4rem .9rem; background: var(--surface-2); border-bottom: 1px solid var(--hairline); flex-shrink: 0; }
    .ai-chat-model-select { flex: 1; font-size: .75rem; border: 1px solid var(--hairline-2); border-radius: .35rem; padding: .25rem .45rem; background: #fff; color: var(--ink); outline: none; }
    .ai-chat-model-select:focus { border-color: var(--accent); }

    .ai-chat-messages { flex: 1; overflow-y: auto; padding: .85rem .9rem; display: flex; flex-direction: column; gap: .55rem; }
    .ai-chat-msg { display: flex; max-width: 88%; }
    .ai-chat-msg-user { align-self: flex-end; }
    .ai-chat-msg-bot  { align-self: flex-start; }
    .ai-chat-msg-content { padding: .58rem .8rem; border-radius: var(--r-md); font-size: .84rem; line-height: 1.45; word-break: break-word; }
    .ai-chat-msg-user .ai-chat-msg-content { background: var(--accent-deep); color: #fff; border-bottom-right-radius: .25rem; }
    .ai-chat-msg-bot .ai-chat-msg-content { background: var(--surface-2); color: var(--ink); border-bottom-left-radius: .25rem; }
    .ai-chat-msg-bot .ai-chat-msg-content strong { color: var(--accent-ink); }
    .ai-chat-msg-bot .ai-chat-msg-content ul, .ai-chat-msg-bot .ai-chat-msg-content ol { margin: .3rem 0 .1rem 1rem; padding: 0; }

    .ai-chat-typing { display: flex; gap: .25rem; padding: .5rem .75rem; }
    .ai-chat-typing-dot { width: .45rem; height: .45rem; border-radius: 50%; background: var(--accent); opacity: .4; animation: chatBounce .9s infinite; }
    .ai-chat-typing-dot:nth-child(2) { animation-delay: .15s; }
    .ai-chat-typing-dot:nth-child(3) { animation-delay: .3s; }
    @keyframes chatBounce { 0%, 80%, 100% { transform: translateY(0); opacity: .4; } 40% { transform: translateY(-6px); opacity: 1; } }

    .ai-chat-input-bar { display: flex; align-items: flex-end; gap: .45rem; padding: .6rem .8rem; border-top: 1px solid var(--hairline); background: var(--surface-2); flex-shrink: 0; }
    .ai-chat-input { flex: 1; border: 1px solid var(--hairline-2); border-radius: var(--r-sm); padding: .45rem .65rem; font-size: .84rem; resize: none; max-height: 5rem; line-height: 1.4; outline: none; font-family: inherit; background: #fff; }
    .ai-chat-input:focus { border-color: var(--accent); box-shadow: var(--ring); }
    .ai-chat-send-btn { width: 2.2rem; height: 2.2rem; border-radius: var(--r-sm); border: none; background: var(--accent); color: #04211C; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: opacity .15s, transform .15s; }
    .ai-chat-send-btn:disabled { opacity: .4; cursor: default; }
    .ai-chat-send-btn:not(:disabled):hover { transform: scale(1.08); }

    #ai-chat-history-overlay.overlay-open { transform: translateX(0) !important; }
    .chat-history-item { padding: 0.75rem 1rem; border-bottom: 1px solid var(--hairline); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.15s; }
    .chat-history-item:hover { background: var(--surface-2); }
    .chat-history-item.active { background: var(--accent-wash); border-left: 3px solid var(--accent); }
    .chat-history-item-title { font-size: 0.8rem; font-weight: 600; color: var(--ink); margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-history-item-meta { font-size: 0.7rem; color: var(--muted); }

    @media (max-width: 576px) {
        #ai-chat-panel { right: 0; bottom: 0; width: 100%; height: 100vh; border-radius: 0; }
        #ai-chat-fab { bottom: 1rem; right: 1rem; }
    }
</style>

<script>
$(function () {
    const CSRF       = $('meta[name="csrf-token"]').attr('content');
    const CHAT_URL   = '{{ route("chat.send") }}';
    const MODELS_URL = '{{ route("chat.models") }}';
    const CLEAR_URL  = '{{ route("chat.clear") }}';
    const HIST_URL   = '{{ route("chat.history") }}';
    const SESSIONS_URL = '{{ route("chat.sessions") }}';
    const RENAME_URL = '{{ route("chat.rename") }}';
    const USER_ID    = '{{ auth()->id() }}';

    const LS_SESSION = 'ai_chat_session_' + USER_ID;
    const LS_MODEL   = 'ai_chat_model_' + USER_ID;

    let sessionId    = localStorage.getItem(LS_SESSION);
    let isSending    = false;

    const $fab      = $('#ai-chat-fab');
    const $panel    = $('#ai-chat-panel');
    const $messages = $('#chatMessages');
    const $input    = $('#chatInput');
    const $sendBtn  = $('#chatSendBtn');
    const $modelSel = $('#chatModelSelect');
    const $overlay  = $('#ai-chat-history-overlay');
    const $histList = $('#chatHistoryList');

    $fab.on('click', function () {
        $panel.toggleClass('ai-chat-hidden');
        if (!$panel.hasClass('ai-chat-hidden')) { $input.trigger('focus'); scrollToBottom(); }
    });
    $('#chatCloseBtn').on('click', () => $panel.addClass('ai-chat-hidden'));

    $('#chatHistoryBtn').on('click', function() { $overlay.addClass('overlay-open'); loadSessionsList(); });
    $('#chatHistoryCloseBtn').on('click', function() { $overlay.removeClass('overlay-open'); });

    function loadSessionsList() {
        $histList.html('<div style="padding:1rem;text-align:center;font-size:0.8rem;color:#667085;">Chargement...</div>');
        $.getJSON(SESSIONS_URL, function(data) {
            $histList.empty();
            if (!data.sessions || data.sessions.length === 0) {
                $histList.html('<div style="padding:1rem;text-align:center;font-size:0.8rem;color:#667085;">Aucune conversation trouvée.</div>');
                return;
            }
            data.sessions.forEach(function(sess) {
                const isActive = parseInt(sessionId) === sess.id;
                const date = new Date(sess.updated_at).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
                const $item = $(`
                    <div class="chat-history-item ${isActive ? 'active' : ''}" data-id="${sess.id}">
                        <div style="flex:1;min-width:0;">
                            <div class="chat-history-item-title">${escapeHtml(sess.title || 'Nouvelle conversation')}</div>
                            <div class="chat-history-item-meta">${date} • ${sess.message_count} msg • ${sess.model ? sess.model.split('/').pop() : 'Default'}</div>
                        </div>
                        <div style="display:flex;align-items:center;">
                            <button class="btn btn-sm btn-link text-secondary p-0 rename-session-btn" title="Renommer" style="margin-left:5px;"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-link text-danger p-0 delete-session-btn" title="Supprimer" style="margin-left:10px;"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                `);
                $item.on('click', function(e) {
                    if ($(e.target).closest('.delete-session-btn, .rename-session-btn').length > 0) return;
                    resumeSession(sess.id, sess.model);
                });
                $item.find('.delete-session-btn').on('click', function(e) {
                    e.stopPropagation();
                    confirmDialog({ message: 'Voulez-vous vraiment supprimer cette conversation ?', variant: 'danger', confirmText: 'Supprimer' }).then(function (ok) {
                        if (!ok) return;
                        $.post(CLEAR_URL, { _token: CSRF, session_id: sess.id }, function() {
                            if (parseInt(sessionId) === sess.id) startNewSession();
                            loadSessionsList();
                        });
                    });
                });
                $item.find('.rename-session-btn').on('click', function(e) {
                    e.stopPropagation();
                    const newTitle = prompt('Nouveau nom de la conversation :', sess.title || 'Nouvelle conversation');
                    if (newTitle && newTitle.trim() !== '') {
                        $.ajax({ url: RENAME_URL, method: 'PUT', data: { _token: CSRF, session_id: sess.id, title: newTitle.trim() } }).done(loadSessionsList);
                    }
                });
                $histList.append($item);
            });
        });
    }

    $.getJSON(MODELS_URL, function (data) {
        $modelSel.empty();
        let savedModel = localStorage.getItem(LS_MODEL);
        if (!savedModel || (data.models && !data.models.includes(savedModel))) savedModel = data.default;
        (data.models || []).forEach(function (m) {
            const label = m.split('/').pop();
            $modelSel.append(`<option value="${m}" ${m === savedModel ? 'selected' : ''}>${label}</option>`);
        });
        localStorage.setItem(LS_MODEL, savedModel);
    });

    $modelSel.on('change', function() { localStorage.setItem(LS_MODEL, $(this).val()); });

    function loadActiveSessionMessages() {
        if (sessionId) {
            $.getJSON(HIST_URL, { session_id: sessionId })
             .done(function(data) {
                 if (data.messages && data.messages.length > 0) {
                     const greeting = $messages.children().first().clone();
                     $messages.empty().append(greeting);
                     data.messages.forEach(function(msg) {
                         const type = msg.role === 'user' ? 'user' : 'bot';
                         const content = type === 'user' ? escapeHtml(msg.content) : formatMarkdown(msg.content);
                         appendMsg(type, content);
                     });
                     scrollToBottom();
                 } else { startNewSession(); }
             })
             .fail(function() { startNewSession(); });
        }
    }
    loadActiveSessionMessages();

    function resumeSession(id, model) {
        sessionId = id;
        localStorage.setItem(LS_SESSION, id);
        if (model) { $modelSel.val(model); localStorage.setItem(LS_MODEL, model); }
        const greeting = $messages.children().first().clone();
        $messages.empty().append(greeting).append('<div style="text-align:center;padding:10px;font-size:0.8rem;color:#888;">Chargement...</div>');
        $overlay.removeClass('overlay-open');
        loadActiveSessionMessages();
    }
    function startNewSession() {
        sessionId = null;
        localStorage.removeItem(LS_SESSION);
        const greeting = $messages.children().first().clone();
        $messages.empty().append(greeting);
        $input.val('').trigger('input');
    }

    $input.on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
        $sendBtn.prop('disabled', !this.value.trim() || isSending);
    });
    $input.on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (!$sendBtn.prop('disabled')) sendMessage(); }
    });
    $sendBtn.on('click', sendMessage);

    function sendMessage() {
        const text = $input.val().trim();
        if (!text || isSending) return;
        isSending = true;
        $sendBtn.prop('disabled', true);
        $input.val('').trigger('input');
        appendMsg('user', escapeHtml(text));
        const $typing = $(`<div class="ai-chat-msg ai-chat-msg-bot" id="chatTyping"><div class="ai-chat-msg-content ai-chat-typing"><span class="ai-chat-typing-dot"></span><span class="ai-chat-typing-dot"></span><span class="ai-chat-typing-dot"></span></div></div>`);
        $messages.append($typing);
        scrollToBottom();
        $.ajax({ url: CHAT_URL, method: 'POST', data: { _token: CSRF, message: text, session_id: sessionId, model: $modelSel.val() }, timeout: 120000 })
        .done(function (resp) {
            $('#chatTyping').remove();
            if (resp.success && resp.reply) {
                sessionId = resp.session_id;
                localStorage.setItem(LS_SESSION, sessionId);
                appendMsg('bot', formatMarkdown(resp.reply));
            } else { appendMsg('bot', '⚠️ ' + (resp.error || 'Erreur inconnue.')); }
        })
        .fail(function (xhr) {
            $('#chatTyping').remove();
            let msg = 'Le service IA est indisponible.';
            if (xhr.responseJSON?.error) msg = xhr.responseJSON.error;
            appendMsg('bot', '⚠️ ' + msg);
        })
        .always(function () { isSending = false; $sendBtn.prop('disabled', !$input.val().trim()); scrollToBottom(); });
    }

    $('#chatClearBtn').on('click', function () { startNewSession(); $overlay.removeClass('overlay-open'); });

    function appendMsg(type, html) {
        const cls = type === 'user' ? 'ai-chat-msg-user' : 'ai-chat-msg-bot';
        $messages.append(`<div class="ai-chat-msg ${cls}"><div class="ai-chat-msg-content">${html}</div></div>`);
        scrollToBottom();
    }
    function scrollToBottom() { const el = $messages[0]; setTimeout(() => el.scrollTop = el.scrollHeight, 50); }
    function escapeHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function formatMarkdown(text) {
        let html = escapeHtml(text);
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/^[-•]\s+(.+)$/gm, '<li>$1</li>');
        html = html.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
        html = html.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');
        html = html.replace(/\n/g, '<br>');
        html = html.replace(/<\/ul>\s*<br>\s*<ul>/g, '');
        return html;
    }
});
</script>
