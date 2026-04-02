(function () {
    'use strict';

    if (window.__hermesAgentWidgetLoaded) {
        return;
    }
    window.__hermesAgentWidgetLoaded = true;

    var aiBaseUrl = window.BASE_URL || '';
    var state = {
        user: null,
        history: [],
        sending: false,
        isOpen: false
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
            '        <div>' +
            '            <div class="hermes-agent-widget__eyebrow">AI Assistant</div>' +
            '            <h2 class="hermes-agent-widget__title">Hermes Agent</h2>' +
            '            <p class="hermes-agent-widget__subtitle">Terhubung ke agent internal Anda.</p>' +
            '        </div>' +
            '        <div class="hermes-agent-widget__header-actions">' +
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

        elements.reset.addEventListener('click', function () {
            resetConversation();
        });

        elements.form.addEventListener('submit', handleSubmit);

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

    function loadConversation() {
        var fallbackMessage = createGreetingMessage();
        try {
            var raw = localStorage.getItem(getHistoryStorageKey());
            if (!raw) {
                state.history = [fallbackMessage];
                return;
            }

            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed) || parsed.length === 0) {
                state.history = [fallbackMessage];
                return;
            }

            state.history = parsed.slice(-40);
        } catch (error) {
            state.history = [fallbackMessage];
        }
    }

    function saveConversation() {
        try {
            localStorage.setItem(getHistoryStorageKey(), JSON.stringify(state.history.slice(-40)));
        } catch (error) {
            console.warn('Hermes Agent: failed to save conversation', error);
        }
    }

    function renderMessages() {
        if (!elements.messages) return;

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
        elements.messages.scrollTop = elements.messages.scrollHeight;
    }

    function formatTimestamp(timestamp) {
        var date = new Date(timestamp || Date.now());
        return date.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });
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
                if (elements.input) {
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
        state.history = [createGreetingMessage()];
        saveConversation();
        renderMessages();
        if (elements.input) {
            elements.input.value = '';
            autoResizeTextarea();
        }
    }

    function pushMessage(role, content, timestamp) {
        state.history.push({
            role: role,
            content: content,
            timestamp: timestamp || Date.now()
        });
        state.history = state.history.slice(-40);
        saveConversation();
        renderMessages();
    }

    async function handleSubmit(event) {
        if (event && typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (state.sending || !elements.input) {
            return;
        }

        var message = elements.input.value.trim();
        if (!message) {
            return;
        }

        pushMessage('user', message);
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
                    page_context: {
                        path: window.location.pathname || '',
                        title: document.title || '',
                        heading: document.querySelector('.page-header-title h5')
                            ? document.querySelector('.page-header-title h5').textContent.trim()
                            : '',
                        role: detectRoleFromPath()
                    }
                })
            });

            var result = await response.json().catch(function () {
                return {};
            });

            if (!response.ok || result.status !== 'ok' || !result.reply) {
                throw new Error(result.error || 'Hermes Agent sedang tidak bisa merespons.');
            }

            pushMessage('assistant', String(result.reply).trim(), (result.timestamp || 0) * 1000 || Date.now());
        } catch (error) {
            pushMessage('system', error.message || 'Terjadi kesalahan saat menghubungi Hermes Agent.');
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