(function () {
    'use strict';

    if (window.__hermesAgentWidgetLoaded) {
        return;
    }
    window.__hermesAgentWidgetLoaded = true;

    var aiBaseUrl = window.BASE_URL || '';
    var MAX_ACTIVE_MESSAGES = 40;
    var MAX_REQUEST_HISTORY = 8;
    var MAX_ARCHIVES = 15;
    var MAX_ARCHIVE_MESSAGES = 60;

    var state = {
        user: null,
        history: [],
        archives: [],
        sending: false,
        isOpen: false,
        pendingOutgoing: null,
        activePane: 'chat',
        selectedArchiveId: null
    };
    var elements = {};

    var roleConfig = {
        admin: {
            label: 'Admin',
            intro: 'Saya bisa bantu seputar stok, approval, user, dan laporan.'
        },
        manager: {
            label: 'Manager',
            intro: 'Saya bisa bantu approval, monitoring peminjaman, dan laporan.'
        },
        pic_barang: {
            label: 'PIC',
            intro: 'Saya bisa bantu update item, stok, dan proses pengembalian.'
        },
        user: {
            label: 'User',
            intro: 'Saya bisa bantu pengajuan pinjam, status, dan pengembalian barang.'
        }
    };

    function detectRoleFromPath() {
        var path = window.location.pathname || '';
        if (/\/admin(\/|$)/.test(path)) return 'admin';
        if (/\/manager(\/|$)/.test(path)) return 'manager';
        if (/\/pic-barang(\/|$)/.test(path)) return 'pic_barang';
        if (/\/user(\/|$)/.test(path)) return 'user';
        return null;
    }

    function createWidgetMarkup() {
        var container = document.createElement('div');
        container.className = 'hermes-agent-widget';
        container.innerHTML =
            '<button class="hermes-agent-widget__fab" type="button" aria-label="Buka Hermes Agent" aria-expanded="false">' +
            '    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
            '        <path d="M12 3C6.48 3 2 6.94 2 11.8c0 2.69 1.37 5.1 3.54 6.72V22l3.26-1.8c1 .28 2.08.43 3.2.43 5.52 0 10-3.94 10-8.8S17.52 3 12 3Z" fill="currentColor" opacity=".24"></path>' +
            '        <path d="M7.8 10.2h8.4M7.8 13.8h5.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"></path>' +
            '    </svg>' +
            '</button>' +
            '<section class="hermes-agent-widget__panel" aria-label="Hermes Agent Chat">' +
            '    <div class="hermes-agent-widget__header">' +
            '        <div class="hermes-agent-widget__header-copy">' +
            '            <div class="hermes-agent-widget__eyebrow">AI Assistant</div>' +
            '            <h2 class="hermes-agent-widget__title">Hermes Agent</h2>' +
            '            <p class="hermes-agent-widget__subtitle">Terhubung ke agent internal Anda.</p>' +
            '        </div>' +
            '        <div class="hermes-agent-widget__header-actions">' +
            '            <button class="hermes-agent-widget__action" type="button" data-action="history" title="Riwayat chat" aria-label="Riwayat chat">Riwayat</button>' +
            '            <button class="hermes-agent-widget__action" type="button" data-action="reset" title="Chat baru" aria-label="Chat baru">Reset</button>' +
            '            <button class="hermes-agent-widget__action" type="button" data-action="close" title="Tutup" aria-label="Tutup">X</button>' +
            '        </div>' +
            '    </div>' +
            '    <div class="hermes-agent-widget__messages" data-messages></div>' +
            '    <form class="hermes-agent-widget__composer" data-form>' +
            '        <div class="hermes-agent-widget__composer-box">' +
            '            <textarea class="hermes-agent-widget__composer-input" rows="1" maxlength="2000" placeholder="Tulis pertanyaan Anda ke Hermes Agent..." data-input></textarea>' +
            '            <button class="hermes-agent-widget__composer-button" type="submit" data-submit>Kirim</button>' +
            '        </div>' +
            '        <div class="hermes-agent-widget__hint">Tekan Enter untuk kirim, Shift + Enter untuk baris baru.</div>' +
            '    </form>' +
            '</section>';

        document.body.appendChild(container);

        elements.container = container;
        elements.fab = container.querySelector('.hermes-agent-widget__fab');
        elements.panel = container.querySelector('.hermes-agent-widget__panel');
        elements.messages = container.querySelector('[data-messages]');
        elements.form = container.querySelector('[data-form]');
        elements.input = container.querySelector('[data-input]');
        elements.submit = container.querySelector('[data-submit]');
        elements.history = container.querySelector('[data-action="history"]');
        elements.reset = container.querySelector('[data-action="reset"]');
        elements.close = container.querySelector('[data-action="close"]');
        elements.subtitle = container.querySelector('.hermes-agent-widget__subtitle');

        bindEvents();
    }

    function bindEvents() {
        elements.fab.addEventListener('click', function () {
            setOpenState(!state.isOpen);
        });

        elements.close.addEventListener('click', function () {
            setOpenState(false);
        });

        elements.history.addEventListener('click', handleHistoryToggle);
        elements.reset.addEventListener('click', handleResetAction);
        elements.form.addEventListener('submit', handleSubmit);
        elements.messages.addEventListener('click', handleArchivePanelClick);

        elements.input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                handleSubmit(event);
            }
        });

        elements.input.addEventListener('input', autoResizeTextarea);
    }

    function autoResizeTextarea() {
        if (!elements.input) return;
        elements.input.style.height = 'auto';
        elements.input.style.height = Math.min(elements.input.scrollHeight, 120) + 'px';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function truncateText(value, maxLength) {
        var text = String(value || '').trim();
        if (text.length <= maxLength) {
            return text;
        }
        return text.slice(0, Math.max(0, maxLength - 1)).trim() + '...';
    }

    function getSavedUser() {
        try {
            var raw = localStorage.getItem('user');
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return null;
            return {
                user_id: parsed.user_id || parsed.id || '',
                nama: parsed.nama || '',
                email: parsed.email || '',
                role: parsed.role || detectRoleFromPath()
            };
        } catch (error) {
            return null;
        }
    }

    function getStoragePrefix() {
        if (!state.user) return 'hermes-agent-anon';
        return 'hermes-agent:' + (state.user.user_id || '0') + ':' + (state.user.role || 'unknown');
    }

    function getHistoryStorageKey() {
        return getStoragePrefix() + ':history';
    }

    function getArchiveStorageKey() {
        return getStoragePrefix() + ':archives';
    }

    function getPanelStorageKey() {
        return getStoragePrefix() + ':open';
    }

    function getRoleDetails(role) {
        return roleConfig[role] || {
            label: 'User',
            intro: 'Saya siap membantu penggunaan aplikasi ini.'
        };
    }

    function createGreetingMessage() {
        var details = getRoleDetails((state.user && state.user.role) || detectRoleFromPath());
        var name = (state.user && state.user.nama) || 'rekan';
        return {
            role: 'assistant',
            content: 'Halo ' + name + '. Saya Hermes Agent. ' + details.intro,
            timestamp: Date.now()
        };
    }

    function normalizeStoredMessages(messages, limit) {
        if (!Array.isArray(messages)) {
            return [];
        }

        return messages
            .filter(function (message) {
                return message && typeof message === 'object';
            })
            .map(function (message) {
                var role = message.role === 'user' ? 'user' : (message.role === 'system' ? 'system' : 'assistant');
                var content = String(message.content || '').trim();
                var timestamp = Number(message.timestamp) || Date.now();

                if (!content) {
                    return null;
                }

                return {
                    role: role,
                    content: content,
                    timestamp: timestamp
                };
            })
            .filter(Boolean)
            .slice(-(limit || MAX_ACTIVE_MESSAGES));
    }

    function hasMeaningfulConversation(messages) {
        return normalizeStoredMessages(messages, MAX_ARCHIVE_MESSAGES).some(function (message) {
            return message.role === 'user';
        });
    }

    function formatTimestamp(timestamp) {
        var date = new Date(timestamp || Date.now());
        return date.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatArchiveTimestamp(timestamp) {
        var date = new Date(timestamp || Date.now());
        return date.toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function createArchiveTitle(messages) {
        var normalized = normalizeStoredMessages(messages, MAX_ARCHIVE_MESSAGES);
        var firstUserMessage = normalized.find(function (message) {
            return message.role === 'user';
        });

        if (firstUserMessage) {
            return truncateText(firstUserMessage.content, 42);
        }

        return 'Percakapan ' + formatArchiveTimestamp(normalized[normalized.length - 1] ? normalized[normalized.length - 1].timestamp : Date.now());
    }

    function createArchivePreview(messages) {
        var normalized = normalizeStoredMessages(messages, MAX_ARCHIVE_MESSAGES);
        var previewMessage = normalized.find(function (message) {
            return message.role === 'user';
        }) || normalized.find(function (message) {
            return message.role === 'assistant';
        }) || normalized[0];

        return previewMessage ? truncateText(previewMessage.content, 120) : 'Percakapan tanpa isi.';
    }

    function buildArchiveRecord(messages) {
        var normalized = normalizeStoredMessages(messages, MAX_ARCHIVE_MESSAGES);
        if (!hasMeaningfulConversation(normalized)) {
            return null;
        }

        return {
            id: 'conv-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8),
            title: createArchiveTitle(normalized),
            preview: createArchivePreview(normalized),
            created_at: normalized[0] ? normalized[0].timestamp : Date.now(),
            updated_at: normalized[normalized.length - 1] ? normalized[normalized.length - 1].timestamp : Date.now(),
            count: normalized.length,
            messages: normalized
        };
    }

    function conversationsMatch(leftMessages, rightMessages) {
        var left = normalizeStoredMessages(leftMessages, MAX_ARCHIVE_MESSAGES);
        var right = normalizeStoredMessages(rightMessages, MAX_ARCHIVE_MESSAGES);

        if (left.length !== right.length) {
            return false;
        }

        for (var i = 0; i < left.length; i += 1) {
            if (left[i].role !== right[i].role || left[i].content !== right[i].content) {
                return false;
            }
        }

        return true;
    }

    function archiveCurrentConversation() {
        var record = buildArchiveRecord(state.history);
        if (!record) {
            return false;
        }

        var alreadyExists = state.archives.some(function (archive) {
            return conversationsMatch(archive.messages, record.messages);
        });
        if (alreadyExists) {
            return false;
        }

        state.archives.unshift(record);
        state.archives = state.archives.slice(0, MAX_ARCHIVES);
        saveArchivedConversations();
        return true;
    }

    function normalizeArchiveRecord(record) {
        if (!record || typeof record !== 'object') {
            return null;
        }

        var messages = normalizeStoredMessages(record.messages, MAX_ARCHIVE_MESSAGES);
        if (!messages.length) {
            return null;
        }

        return {
            id: String(record.id || ('conv-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8))),
            title: truncateText(record.title || createArchiveTitle(messages), 42),
            preview: truncateText(record.preview || createArchivePreview(messages), 120),
            created_at: Number(record.created_at) || messages[0].timestamp || Date.now(),
            updated_at: Number(record.updated_at) || messages[messages.length - 1].timestamp || Date.now(),
            count: Number(record.count) || messages.length,
            messages: messages
        };
    }

    function loadConversation() {
        var fallbackMessage = createGreetingMessage();
        try {
            var raw = localStorage.getItem(getHistoryStorageKey());
            if (!raw) {
                state.history = [fallbackMessage];
                return;
            }

            var parsed = JSON.parse(raw);
            var normalized = normalizeStoredMessages(parsed, MAX_ACTIVE_MESSAGES);
            state.history = normalized.length ? normalized : [fallbackMessage];
        } catch (error) {
            state.history = [fallbackMessage];
        }
    }

    function saveConversation() {
        try {
            localStorage.setItem(getHistoryStorageKey(), JSON.stringify(normalizeStoredMessages(state.history, MAX_ACTIVE_MESSAGES)));
        } catch (error) {
            console.warn('Hermes Agent: failed to save conversation', error);
        }
    }

    function loadArchivedConversations() {
        try {
            var raw = localStorage.getItem(getArchiveStorageKey());
            if (!raw) {
                state.archives = [];
                return;
            }

            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                state.archives = [];
                return;
            }

            state.archives = parsed
                .map(normalizeArchiveRecord)
                .filter(Boolean)
                .slice(0, MAX_ARCHIVES);
        } catch (error) {
            state.archives = [];
        }
    }

    function saveArchivedConversations() {
        try {
            localStorage.setItem(getArchiveStorageKey(), JSON.stringify(state.archives.slice(0, MAX_ARCHIVES)));
        } catch (error) {
            console.warn('Hermes Agent: failed to save archived conversations', error);
        }
    }

    function getArchiveById(archiveId) {
        return state.archives.find(function (archive) {
            return archive.id === archiveId;
        }) || null;
    }

    function syncPaneState() {
        var isChatPane = state.activePane === 'chat';
        elements.container.classList.toggle('is-history-pane', !isChatPane);
        if (elements.form) {
            elements.form.style.display = isChatPane ? '' : 'none';
        }
    }

    function renderChatMessages() {
        if (!state.history.length && !state.sending) {
            elements.messages.innerHTML = '<div class="hermes-agent-widget__empty">Mulai percakapan dengan Hermes Agent.</div>';
            return;
        }

        var html = '';
        state.history.forEach(function (message) {
            var role = message.role === 'user' ? 'user' : (message.role === 'system' ? 'system' : 'assistant');
            html +=
                '<div class="hermes-agent-widget__message-row hermes-agent-widget__message-row--' + role + '">' +
                '    <div class="hermes-agent-widget__bubble hermes-agent-widget__bubble--' + role + '">' +
                '        <div>' + escapeHtml(message.content) + '</div>' +
                '        <div class="hermes-agent-widget__meta">' + formatTimestamp(message.timestamp) + '</div>' +
                '    </div>' +
                '</div>';
        });

        if (state.pendingOutgoing) {
            html +=
                '<div class="hermes-agent-widget__message-row hermes-agent-widget__message-row--user">' +
                '    <div class="hermes-agent-widget__bubble hermes-agent-widget__bubble--user">' +
                '        <div>' + escapeHtml(state.pendingOutgoing.content) + '</div>' +
                '        <div class="hermes-agent-widget__meta">' + formatTimestamp(state.pendingOutgoing.timestamp) + '</div>' +
                '    </div>' +
                '</div>';
        }

        if (state.sending) {
            html +=
                '<div class="hermes-agent-widget__message-row hermes-agent-widget__message-row--assistant">' +
                '    <div class="hermes-agent-widget__bubble hermes-agent-widget__bubble--assistant">' +
                '        <div class="hermes-agent-widget__typing"><span></span><span></span><span></span></div>' +
                '        <div class="hermes-agent-widget__meta">Hermes sedang mengetik...</div>' +
                '    </div>' +
                '</div>';
        }

        elements.messages.innerHTML = html;
    }

    function renderArchiveList() {
        if (!state.archives.length) {
            elements.messages.innerHTML =
                '<div class="hermes-agent-widget__history-shell">' +
                '    <div class="hermes-agent-widget__history-topbar">' +
                '        <div class="hermes-agent-widget__history-topbar-actions">' +
                '            <button class="hermes-agent-widget__history-back" type="button" data-history-action="back">Kembali</button>' +
                '            <button class="hermes-agent-widget__history-button hermes-agent-widget__history-button--primary" type="button" data-history-action="new">Chat Baru</button>' +
                '        </div>' +
                '        <div class="hermes-agent-widget__history-heading">Riwayat Chat</div>' +
                '    </div>' +
                '    <div class="hermes-agent-widget__history-empty">Belum ada percakapan yang tersimpan di riwayat.</div>' +
                '</div>';
            return;
        }

        var cards = state.archives.map(function (archive) {
            return (
                '<article class="hermes-agent-widget__history-card">' +
                '    <div class="hermes-agent-widget__history-card-title">' + escapeHtml(archive.title) + '</div>' +
                '    <div class="hermes-agent-widget__history-card-preview">' + escapeHtml(archive.preview) + '</div>' +
                '    <div class="hermes-agent-widget__history-card-meta">' + escapeHtml(formatArchiveTimestamp(archive.updated_at)) + ' | ' + archive.count + ' pesan</div>' +
                '    <div class="hermes-agent-widget__history-card-actions">' +
                '        <button class="hermes-agent-widget__history-button" type="button" data-history-action="view" data-history-id="' + escapeHtml(archive.id) + '">Lihat</button>' +
                '        <button class="hermes-agent-widget__history-button" type="button" data-history-action="load" data-history-id="' + escapeHtml(archive.id) + '">Muat</button>' +
                '        <button class="hermes-agent-widget__history-button hermes-agent-widget__history-button--danger" type="button" data-history-action="delete" data-history-id="' + escapeHtml(archive.id) + '">Hapus</button>' +
                '    </div>' +
                '</article>'
            );
        }).join('');

        elements.messages.innerHTML =
            '<div class="hermes-agent-widget__history-shell">' +
            '    <div class="hermes-agent-widget__history-topbar">' +
            '        <div class="hermes-agent-widget__history-topbar-actions">' +
            '            <button class="hermes-agent-widget__history-back" type="button" data-history-action="back">Kembali</button>' +
            '            <button class="hermes-agent-widget__history-button hermes-agent-widget__history-button--primary" type="button" data-history-action="new">Chat Baru</button>' +
            '        </div>' +
            '        <div class="hermes-agent-widget__history-heading">Riwayat Chat</div>' +
            '    </div>' +
            '    <div class="hermes-agent-widget__history-list">' + cards + '</div>' +
            '</div>';
    }

    function renderArchiveDetail() {
        var archive = getArchiveById(state.selectedArchiveId);
        if (!archive) {
            state.activePane = 'history-list';
            state.selectedArchiveId = null;
            renderMessages();
            return;
        }

        var html =
            '<div class="hermes-agent-widget__history-shell">' +
            '    <div class="hermes-agent-widget__history-topbar">' +
            '        <button class="hermes-agent-widget__history-back" type="button" data-history-action="list">Kembali</button>' +
            '        <div>' +
            '            <div class="hermes-agent-widget__history-heading">' + escapeHtml(archive.title) + '</div>' +
            '            <div class="hermes-agent-widget__history-subheading">' + escapeHtml(formatArchiveTimestamp(archive.updated_at)) + ' | ' + archive.count + ' pesan</div>' +
            '        </div>' +
            '    </div>' +
            '    <div class="hermes-agent-widget__history-detail-actions">' +
            '        <button class="hermes-agent-widget__history-button hermes-agent-widget__history-button--primary" type="button" data-history-action="new">Chat Baru</button>' +
            '        <button class="hermes-agent-widget__history-button" type="button" data-history-action="load" data-history-id="' + escapeHtml(archive.id) + '">Muat ke Chat</button>' +
            '        <button class="hermes-agent-widget__history-button hermes-agent-widget__history-button--danger" type="button" data-history-action="delete" data-history-id="' + escapeHtml(archive.id) + '">Hapus</button>' +
            '    </div>' +
            '    <div class="hermes-agent-widget__history-transcript">';

        archive.messages.forEach(function (message) {
            var role = message.role === 'user' ? 'user' : (message.role === 'system' ? 'system' : 'assistant');
            html +=
                '<div class="hermes-agent-widget__message-row hermes-agent-widget__message-row--' + role + '">' +
                '    <div class="hermes-agent-widget__bubble hermes-agent-widget__bubble--' + role + '">' +
                '        <div>' + escapeHtml(message.content) + '</div>' +
                '        <div class="hermes-agent-widget__meta">' + formatTimestamp(message.timestamp) + '</div>' +
                '    </div>' +
                '</div>';
        });

        html += '    </div></div>';
        elements.messages.innerHTML = html;
    }

    function renderMessages() {
        if (!elements.messages) return;

        syncPaneState();

        if (state.activePane === 'history-list') {
            renderArchiveList();
        } else if (state.activePane === 'history-detail') {
            renderArchiveDetail();
        } else {
            renderChatMessages();
        }

        elements.messages.scrollTop = state.activePane === 'chat' ? elements.messages.scrollHeight : 0;
    }

    function setOpenState(isOpen) {
        state.isOpen = !!isOpen;
        elements.container.classList.toggle('is-open', state.isOpen);
        elements.fab.setAttribute('aria-expanded', state.isOpen ? 'true' : 'false');

        try {
            localStorage.setItem(getPanelStorageKey(), state.isOpen ? '1' : '0');
        } catch (error) {
            console.warn('Hermes Agent: failed to persist panel state', error);
        }

        if (state.isOpen) {
            renderMessages();
            setTimeout(function () {
                if (elements.input && state.activePane === 'chat') {
                    elements.input.focus();
                }
            }, 120);
        }
    }

    function restoreOpenState() {
        try {
            state.isOpen = localStorage.getItem(getPanelStorageKey()) === '1';
        } catch (error) {
            state.isOpen = false;
        }
        setOpenState(state.isOpen);
    }

    function updateHeader() {
        if (!elements.subtitle) return;
        var role = (state.user && state.user.role) || detectRoleFromPath();
        var details = getRoleDetails(role);
        var name = (state.user && state.user.nama) || 'User';
        elements.subtitle.textContent = name + ' | Role ' + details.label;
    }

    function resetConversation() {
        state.pendingOutgoing = null;
        state.activePane = 'chat';
        state.selectedArchiveId = null;
        state.history = [createGreetingMessage()];
        saveConversation();
        renderMessages();
        if (elements.input) {
            elements.input.value = '';
            autoResizeTextarea();
        }
    }

    function focusComposerSoon() {
        if (!elements.input) {
            return;
        }

        setTimeout(function () {
            elements.input.focus();
        }, 120);
    }

    function startFreshConversation() {
        if (hasMeaningfulConversation(state.history)) {
            archiveCurrentConversation();
        }

        resetConversation();
        focusComposerSoon();
    }

    async function revokeSensitiveAccess(reason) {
        var response = await fetch(aiBaseUrl + '/api/ai/lock.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                reason: reason || 'manual'
            })
        });

        var result = await response.json().catch(function () {
            return {};
        });

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Gagal menutup akses sensitif Hermes Agent.');
        }

        return result;
    }

    async function handleResetAction() {
        if (state.sending) {
            return;
        }

        var archived = archiveCurrentConversation();
        resetConversation();

        try {
            var result = await revokeSensitiveAccess('reset');
            var notices = [];
            if (archived) {
                notices.push('Percakapan sebelumnya disimpan ke riwayat.');
            }
            if (result.revoked) {
                notices.push('Akses sensitif ditutup.');
            }
            if (!notices.length) {
                notices.push('Chat baru dimulai.');
            }
            pushMessage('system', notices.join(' '));
        } catch (error) {
            pushMessage('system', archived ? 'Percakapan sebelumnya disimpan ke riwayat. Chat baru dimulai, tetapi akses sensitif belum berhasil ditutup.' : (error.message || 'Chat baru dimulai.'));
        }
    }


    function handleHistoryToggle() {
        if (state.activePane === 'chat') {
            state.activePane = 'history-list';
            state.selectedArchiveId = null;
        } else {
            state.activePane = 'chat';
            state.selectedArchiveId = null;
        }

        renderMessages();
    }

    function handleArchivePanelClick(event) {
        var trigger = event.target.closest('[data-history-action]');
        if (!trigger) {
            return;
        }

        var action = trigger.getAttribute('data-history-action');
        var archiveId = trigger.getAttribute('data-history-id');

        if (action === 'back') {
            state.activePane = 'chat';
            state.selectedArchiveId = null;
            renderMessages();
            return;
        }

        if (action === 'list') {
            state.activePane = 'history-list';
            state.selectedArchiveId = null;
            renderMessages();
            return;
        }

        if (action === 'view' && archiveId) {
            state.activePane = 'history-detail';
            state.selectedArchiveId = archiveId;
            renderMessages();
            return;
        }

        if (action === 'new') {
            startFreshConversation();
            return;
        }

        if (action === 'load' && archiveId) {
            loadArchiveConversation(archiveId);
            return;
        }

        if (action === 'delete' && archiveId) {
            deleteArchiveConversation(archiveId);
        }
    }

    function loadArchiveConversation(archiveId) {
        if (state.sending) {
            return;
        }

        var archive = getArchiveById(archiveId);
        if (!archive) {
            return;
        }

        var currentHadMeaningfulContent = hasMeaningfulConversation(state.history);
        if (currentHadMeaningfulContent && !conversationsMatch(state.history, archive.messages)) {
            archiveCurrentConversation();
        }

        state.pendingOutgoing = null;
        state.activePane = 'chat';
        state.selectedArchiveId = null;
        state.history = normalizeStoredMessages(archive.messages, MAX_ACTIVE_MESSAGES);
        saveConversation();
        renderMessages();

        if (elements.input) {
            setTimeout(function () {
                elements.input.focus();
            }, 120);
        }
    }

    function deleteArchiveConversation(archiveId) {
        var archive = getArchiveById(archiveId);
        if (!archive) {
            return;
        }

        if (window.confirm('Hapus riwayat chat ini?')) {
            state.archives = state.archives.filter(function (item) {
                return item.id !== archiveId;
            });
            saveArchivedConversations();

            if (state.selectedArchiveId === archiveId) {
                state.selectedArchiveId = null;
                state.activePane = 'history-list';
            }

            renderMessages();
        }
    }

    function pushMessage(role, content, timestamp) {
        state.history.push({
            role: role,
            content: content,
            timestamp: timestamp || Date.now()
        });
        state.history = normalizeStoredMessages(state.history, MAX_ACTIVE_MESSAGES);
        saveConversation();
        renderMessages();
    }

    function buildRequestHistory() {
        return state.history
            .filter(function (message) {
                return message && (message.role === 'user' || message.role === 'assistant');
            })
            .slice(-MAX_REQUEST_HISTORY)
            .map(function (message) {
                var content = String(message.content || '').trim();
                if (!content) {
                    return null;
                }

                return {
                    role: message.role,
                    content: content.slice(0, 1200)
                };
            })
            .filter(Boolean);
    }

    function isSnapshotElementVisible(element) {
        if (!element || typeof element.getClientRects !== 'function') {
            return false;
        }

        return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    }

    function collectSnapshotTexts(selectors, maxItems) {
        var items = [];
        var seen = {};

        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (element) {
                if (!isSnapshotElementVisible(element)) {
                    return;
                }

                var text = String(element.textContent || '').replace(/\s+/g, ' ').trim();
                if (!text || text.length > 80 || seen[text]) {
                    return;
                }

                seen[text] = true;
                items.push(text);
            });
        });

        return items.slice(0, maxItems || 10);
    }

    function normalizeSnapshotText(value, maxLength) {
        var text = String(value || '').replace(/\s+/g, ' ').trim();
        if (!text) {
            return '';
        }

        if (maxLength && text.length > maxLength) {
            return text.slice(0, maxLength);
        }

        return text;
    }

    function collectSnapshotFilterLabels(maxItems) {
        var items = [];
        var seen = {};

        document.querySelectorAll('table thead input, table thead select, .card input, .card select').forEach(function (element) {
            if (!isSnapshotElementVisible(element)) {
                return;
            }

            var text = normalizeSnapshotText(
                element.getAttribute('placeholder')
                || element.getAttribute('aria-label')
                || element.name
                || element.id
                || '',
                80
            );

            if (!text || text.length > 80 || seen[text]) {
                return;
            }

            seen[text] = true;
            items.push(text);
        });

        return items.slice(0, maxItems || 12);
    }

    function collectSnapshotActiveFilters(maxItems) {
        var items = [];
        var seen = {};

        document.querySelectorAll('table thead input, table thead select, input[id*="filter"], select[id*="filter"], input[name*="filter"], select[name*="filter"]').forEach(function (element) {
            if (!isSnapshotElementVisible(element)) {
                return;
            }

            var tagName = String(element.tagName || '').toLowerCase();
            var inputType = String(element.type || '').toLowerCase();
            if (inputType === 'password' || inputType === 'hidden') {
                return;
            }

            var key = normalizeSnapshotText(
                element.name
                || element.id
                || element.getAttribute('aria-label')
                || element.getAttribute('placeholder')
                || '',
                60
            );
            if (!key) {
                return;
            }

            var value = '';
            if (tagName === 'select') {
                var selectedOption = element.options && element.selectedIndex >= 0
                    ? element.options[element.selectedIndex]
                    : null;
                value = normalizeSnapshotText(
                    element.value
                    || (selectedOption ? selectedOption.textContent : ''),
                    60
                );
            } else {
                value = normalizeSnapshotText(element.value, 60);
            }

            if (!value) {
                return;
            }

            var item = key + '=' + value;
            if (seen[item]) {
                return;
            }

            seen[item] = true;
            items.push(item);
        });

        return items.slice(0, maxItems || 12);
    }

    function collectSnapshotTableFacts(maxItems) {
        var items = [];
        var seen = {};

        ['#showingInfo', '.dataTables_info', '#filterRoleLabel'].forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (element) {
                if (!isSnapshotElementVisible(element)) {
                    return;
                }

                var text = normalizeSnapshotText(element.textContent || '', 120);
                if (!text || seen[text]) {
                    return;
                }

                seen[text] = true;
                items.push(text);
            });
        });

        return items.slice(0, maxItems || 8);
    }

    function collectSnapshotFormMetadata(maxItems) {
        var items = [];
        var seen = {};

        document.querySelectorAll('form').forEach(function (form) {
            if (!isSnapshotElementVisible(form)) {
                return;
            }

            var label = String(
                form.getAttribute('id')
                || form.getAttribute('name')
                || form.getAttribute('action')
                || ''
            ).replace(/\s+/g, ' ').trim();

            if (!label || label.length > 100 || seen[label]) {
                return;
            }

            seen[label] = true;
            items.push(label);
        });

        return items.slice(0, maxItems || 10);
    }

    function collectSnapshotStructureStats() {
        var stats = [];
        var tableCount = document.querySelectorAll('table').length;
        var formCount = document.querySelectorAll('form').length;
        var modalCount = document.querySelectorAll('.modal').length;
        var buttonCount = document.querySelectorAll('button, .btn, a.btn').length;

        stats.push('tables=' + tableCount);
        stats.push('forms=' + formCount);
        stats.push('modals=' + modalCount);
        stats.push('actions=' + buttonCount);

        return stats;
    }

    function getRouteSegments() {
        return String(window.location.pathname || '')
            .split('/')
            .filter(function (segment) {
                return !!segment;
            })
            .slice(-8);
    }

    function buildPageUiSnapshot() {
        return {
            breadcrumbs: collectSnapshotTexts(['.breadcrumb-item', '.breadcrumb li'], 8),
            cards: collectSnapshotTexts(['.page-header-title h5', '.card-header h3', '.card-header h4', '.card-header h5', '.card h3', '.card h4', '.card h5'], 10),
            buttons: collectSnapshotTexts(['button', '.btn', 'a.btn'], 14),
            table_headers: collectSnapshotTexts(['table thead tr:first-child th'], 14),
            filters: collectSnapshotFilterLabels(12),
            active_filters: collectSnapshotActiveFilters(12),
            labels: collectSnapshotTexts(['form label', '.modal label'], 14),
            links: collectSnapshotTexts(['nav a', '.nxl-submenu a', '.breadcrumb a', 'a[href]:not(.btn)'], 14),
            forms: collectSnapshotFormMetadata(10),
            modals: collectSnapshotTexts(['.modal .modal-title'], 10),
            sections: collectSnapshotTexts(['section h1', 'section h2', 'section h3', '.card-body h3', '.card-body h4', '.card-body h5'], 12),
            stats: collectSnapshotStructureStats(),
            table_facts: collectSnapshotTableFacts(8)
        };
    }

    async function handleSubmit(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (state.sending || !elements.input || state.activePane !== 'chat') {
            return;
        }

        var message = elements.input.value.trim();
        if (!message) {
            return;
        }

        var requestHistory = buildRequestHistory();
        var submittedAt = Date.now();

        state.pendingOutgoing = {
            content: 'Pesan sedang dikirim...',
            timestamp: submittedAt
        };
        elements.input.value = '';
        autoResizeTextarea();
        state.sending = true;
        elements.submit.disabled = true;
        renderMessages();

        try {
            var response = await fetch(aiBaseUrl + '/api/ai/chat.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    history: requestHistory,
                    page_context: {
                        path: window.location.pathname || '',
                        query: window.location.search || '',
                        title: document.title || '',
                        heading: document.querySelector('.page-header-title h5')
                            ? document.querySelector('.page-header-title h5').textContent.trim()
                            : '',
                        role: detectRoleFromPath(),
                        route_segments: getRouteSegments(),
                        ui_snapshot: buildPageUiSnapshot()
                    }
                })
            });

            var result = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || result.status !== 'ok' || !result.reply) {
                var requestError = new Error(result.error || 'Hermes Agent sedang tidak bisa merespons.');
                requestError.result = result;
                throw requestError;
            }

            state.pendingOutgoing = null;
            pushMessage('user', String(result.user_message_display || message).trim() || 'Pesan terkirim.', submittedAt);
            pushMessage('assistant', String(result.reply).trim(), (result.timestamp || 0) * 1000 || Date.now());
        } catch (error) {
            state.pendingOutgoing = null;

            if (error && error.result && error.result.user_message_display) {
                pushMessage('user', String(error.result.user_message_display).trim() || 'Pesan terkirim.', submittedAt);
            }

            pushMessage('system', (error && error.message) || 'Terjadi kesalahan saat menghubungi Hermes Agent.');
        } finally {
            state.sending = false;
            elements.submit.disabled = false;
            renderMessages();
        }
    }

    async function hydrateCurrentUser() {
        var fallbackUser = getSavedUser();
        if (fallbackUser) {
            state.user = fallbackUser;
            updateHeader();
            loadConversation();
            loadArchivedConversations();
            renderMessages();
        }

        try {
            var response = await fetch(aiBaseUrl + '/api/user/get-current-user.php', {
                credentials: 'same-origin'
            });
            if (!response.ok) {
                throw new Error('Failed to load current user');
            }
            var data = await response.json();
            if (!data || !data.success) {
                throw new Error('Failed to load current user');
            }

            state.user = {
                user_id: data.user_id || '',
                nama: data.nama || '',
                email: data.email || '',
                role: data.role || detectRoleFromPath()
            };

            updateHeader();
            loadConversation();
            loadArchivedConversations();
            renderMessages();
            restoreOpenState();
        } catch (error) {
            if (!state.user) {
                state.user = {
                    user_id: '',
                    nama: 'User',
                    email: '',
                    role: detectRoleFromPath()
                };
                updateHeader();
                loadConversation();
                loadArchivedConversations();
                renderMessages();
                restoreOpenState();
            }
        }
    }

    function initializeWidget() {
        if (!detectRoleFromPath()) {
            return;
        }

        createWidgetMarkup();
        updateHeader();
        loadConversation();
        loadArchivedConversations();
        renderMessages();
        restoreOpenState();
        hydrateCurrentUser();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeWidget);
    } else {
        initializeWidget();
    }
})();


