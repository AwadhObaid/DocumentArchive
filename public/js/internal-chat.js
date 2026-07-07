(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const widget = document.getElementById('internalChatWidget');
        if (!widget) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const ready = widget.dataset.ready === '1';
        const canSend = widget.dataset.canSend === '1';
        const pollSeconds = Math.max(3, parseInt(widget.dataset.pollSeconds || '5', 10));
        const urls = {
            bootstrap: widget.dataset.bootstrapUrl,
            users: widget.dataset.usersUrl,
            poll: widget.dataset.pollUrl,
            messagesTemplate: widget.dataset.messagesUrlTemplate,
            send: widget.dataset.sendUrl,
            readTemplate: widget.dataset.readUrlTemplate
        };

        const launcher = widget.querySelector('[data-chat-open]');
        const panel = widget.querySelector('[data-chat-panel]');
        const closeButton = widget.querySelector('[data-chat-close]');
        const totalBadge = widget.querySelector('[data-chat-total]');
        const alertBox = widget.querySelector('[data-chat-alert]');
        const usersBox = widget.querySelector('[data-chat-users]');
        const searchInput = widget.querySelector('[data-chat-user-search]');
        const messagesBox = widget.querySelector('[data-chat-messages]');
        const activeName = widget.querySelector('[data-chat-active-name]');
        const activeSubtitle = widget.querySelector('[data-chat-active-subtitle]');
        const form = widget.querySelector('[data-chat-form]');
        const input = widget.querySelector('[data-chat-input]');

        const state = {
            open: false,
            users: [],
            activeUserId: null,
            activeUserName: '',
            lastMessageId: 0,
            loadingMessages: false,
            pollTimer: null
        };

        function urlFor(template, userId) {
            return (template || '').replace('__USER__', encodeURIComponent(String(userId)));
        }

        function showAlert(message) {
            if (!alertBox) return;
            if (!message) {
                alertBox.hidden = true;
                alertBox.textContent = '';
                return;
            }
            alertBox.hidden = false;
            alertBox.textContent = message;
        }

        async function requestJson(url, options) {
            const response = await fetch(url, Object.assign({
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }, options || {}));

            let data = null;
            try { data = await response.json(); } catch (error) { data = null; }

            if (!response.ok) {
                const message = data && data.message ? data.message : 'تعذر تنفيذ الطلب. حاول مرة أخرى.';
                throw new Error(message);
            }

            return data || {};
        }

        function postJson(url, payload) {
            return requestJson(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload || {})
            });
        }

        function setUnreadTotal(total) {
            const value = parseInt(total || 0, 10);
            if (!totalBadge || !launcher) return;
            if (value > 0) {
                totalBadge.hidden = false;
                totalBadge.textContent = value > 99 ? '99+' : String(value);
                launcher.classList.add('has-unread');
            } else {
                totalBadge.hidden = true;
                totalBadge.textContent = '0';
                launcher.classList.remove('has-unread');
            }
        }

        function initials(name) {
            const value = String(name || 'م').trim();
            return value ? value.slice(0, 1) : 'م';
        }

        function renderUsers() {
            if (!usersBox) return;
            const filter = String(searchInput?.value || '').trim().toLowerCase();
            const filtered = state.users.filter(function (user) {
                const haystack = ((user.name || '') + ' ' + (user.username || '')).toLowerCase();
                return !filter || haystack.includes(filter);
            });

            usersBox.innerHTML = '';
            if (!filtered.length) {
                const empty = document.createElement('div');
                empty.className = 'internal-chat-empty';
                empty.textContent = 'لا يوجد مستخدمون متاحون.';
                usersBox.appendChild(empty);
                return;
            }

            filtered.forEach(function (user) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'internal-chat-user' + (String(state.activeUserId) === String(user.id) ? ' active' : '');
                button.dataset.userId = user.id;

                const avatar = document.createElement('span');
                avatar.className = 'internal-chat-avatar';
                avatar.textContent = initials(user.name);

                const text = document.createElement('span');
                const name = document.createElement('span');
                name.className = 'internal-chat-user-name';
                name.textContent = user.name || ('مستخدم #' + user.id);
                const preview = document.createElement('span');
                preview.className = 'internal-chat-user-preview';
                preview.textContent = user.latest_preview || user.role_name || 'مستخدم نشط';
                text.appendChild(name);
                text.appendChild(preview);

                button.appendChild(avatar);
                button.appendChild(text);

                if (parseInt(user.unread_count || 0, 10) > 0) {
                    const badge = document.createElement('span');
                    badge.className = 'internal-chat-user-unread';
                    badge.textContent = user.unread_count > 99 ? '99+' : String(user.unread_count);
                    button.appendChild(badge);
                } else {
                    const spacer = document.createElement('span');
                    button.appendChild(spacer);
                }

                button.addEventListener('click', function () {
                    selectUser(user.id, user.name || ('مستخدم #' + user.id));
                });

                usersBox.appendChild(button);
            });
        }

        function updateUsers(users) {
            if (Array.isArray(users)) {
                state.users = users;
                renderUsers();
            }
        }

        function messageExists(id) {
            return !!messagesBox?.querySelector('[data-message-id="' + id + '"]');
        }

        function appendMessage(message) {
            if (!messagesBox || !message || !message.id || messageExists(message.id)) return;

            const empty = messagesBox.querySelector('.internal-chat-empty');
            if (empty) empty.remove();

            const wrapper = document.createElement('div');
            wrapper.className = 'internal-chat-message ' + (message.direction === 'outgoing' ? 'outgoing' : 'incoming');
            wrapper.dataset.messageId = message.id;

            const bubble = document.createElement('div');
            bubble.className = 'internal-chat-bubble';

            const body = document.createElement('div');
            body.textContent = message.body || '';

            const meta = document.createElement('div');
            meta.className = 'internal-chat-meta';
            meta.textContent = (message.time || '') + (message.direction === 'outgoing' ? (message.read ? ' · تمت القراءة' : ' · مرسلة') : '');

            bubble.appendChild(body);
            bubble.appendChild(meta);
            wrapper.appendChild(bubble);
            messagesBox.appendChild(wrapper);
            state.lastMessageId = Math.max(state.lastMessageId, parseInt(message.id, 10));
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }

        function renderMessages(messages, reset) {
            if (!messagesBox) return;
            if (reset) {
                messagesBox.innerHTML = '';
                state.lastMessageId = 0;
            }

            if (!Array.isArray(messages) || !messages.length) {
                if (reset) {
                    const empty = document.createElement('div');
                    empty.className = 'internal-chat-empty internal-chat-empty-large';
                    empty.textContent = 'لا توجد رسائل بعد. ابدأ المحادثة برسالة قصيرة.';
                    messagesBox.appendChild(empty);
                }
                return;
            }

            messages.forEach(appendMessage);
        }

        async function loadBootstrap() {
            if (!ready) {
                showAlert('جدول الدردشة غير موجود بعد. نفّذ php artisan migrate ثم حدّث الصفحة.');
                return;
            }

            try {
                const data = await requestJson(urls.bootstrap);
                showAlert('');
                setUnreadTotal(data.unread_total || 0);
                updateUsers(data.users || []);
            } catch (error) {
                showAlert(error.message);
            }
        }

        async function selectUser(userId, userName) {
            if (!userId || state.loadingMessages) return;
            state.activeUserId = userId;
            state.activeUserName = userName || '';
            state.loadingMessages = true;
            renderUsers();
            if (activeName) activeName.textContent = state.activeUserName || 'محادثة';
            if (activeSubtitle) activeSubtitle.textContent = 'محادثة مباشرة محفوظة داخل النظام';
            if (input && canSend) {
                input.disabled = false;
                input.focus();
            }

            try {
                const data = await requestJson(urlFor(urls.messagesTemplate, userId));
                renderMessages(data.messages || [], true);
                setUnreadTotal(data.unread_total || 0);
                updateUsers(data.users || []);
            } catch (error) {
                showAlert(error.message);
            } finally {
                state.loadingMessages = false;
            }
        }

        async function sendMessage(event) {
            event.preventDefault();
            if (!canSend) {
                showAlert('ليست لديك صلاحية إرسال رسائل الدردشة.');
                return;
            }
            if (!state.activeUserId) {
                showAlert('اختر مستخدمًا قبل إرسال الرسالة.');
                return;
            }
            const body = String(input?.value || '').trim();
            if (!body) return;

            const submitButton = form?.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            try {
                const data = await postJson(urls.send, { receiver_id: state.activeUserId, body: body });
                input.value = '';
                appendMessage(data.message);
                setUnreadTotal(data.unread_total || 0);
                updateUsers(data.users || []);
                showAlert('');
            } catch (error) {
                showAlert(error.message);
            } finally {
                if (submitButton) submitButton.disabled = false;
                if (input) input.focus();
            }
        }

        async function poll() {
            if (!ready) return;
            const params = new URLSearchParams();
            if (state.activeUserId) {
                params.set('with_user_id', String(state.activeUserId));
                params.set('after_id', String(state.lastMessageId || 0));
            }

            try {
                const data = await requestJson(urls.poll + (params.toString() ? ('?' + params.toString()) : ''));
                setUnreadTotal(data.unread_total || 0);
                updateUsers(data.users || []);
                if (state.activeUserId && Array.isArray(data.messages) && data.messages.length) {
                    renderMessages(data.messages, false);
                }
                showAlert('');
            } catch (error) {
                // Keep the widget quiet on transient polling errors, but show clear auth/permission errors.
                if (/صلاحية|غير مفعلة|migrate|جلسة|login/i.test(error.message)) {
                    showAlert(error.message);
                }
            }
        }

        function openPanel() {
            state.open = true;
            if (panel) panel.hidden = false;
            loadBootstrap();
            if (searchInput) searchInput.focus();
        }

        function closePanel() {
            state.open = false;
            if (panel) panel.hidden = true;
        }

        if (launcher) {
            launcher.addEventListener('click', function () {
                if (panel && !panel.hidden) closePanel(); else openPanel();
            });
        }

        if (closeButton) closeButton.addEventListener('click', closePanel);
        if (searchInput) searchInput.addEventListener('input', renderUsers);
        if (form) form.addEventListener('submit', sendMessage);
        if (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form?.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }

        loadBootstrap();
        state.pollTimer = window.setInterval(poll, pollSeconds * 1000);
    });
})();
