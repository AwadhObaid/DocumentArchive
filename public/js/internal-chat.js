(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const widget = document.getElementById('internalChatWidget');
        if (!widget) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const ready = widget.dataset.ready === '1';
        const canSend = widget.dataset.canSend === '1';
        const pollSeconds = Math.max(3, parseInt(widget.dataset.pollSeconds || '5', 10));
        const soundEnabled = widget.dataset.soundEnabled === '1';
        const soundVolume = Math.max(0, Math.min(100, parseInt(widget.dataset.soundVolume || '85', 10))) / 100;
        const soundStorageKey = 'documentarchive.internalChat.soundMuted';
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
        const soundToggleButton = widget.querySelector('[data-chat-sound-toggle]');
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
            pollTimer: null,
            lastUnreadTotal: null,
            audioUnlocked: false,
            soundMuted: window.localStorage.getItem(soundStorageKey) === '1',
            audioContext: null,
            soundVolume: soundVolume,
            messages: new Map()
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


        function updateSoundButton() {
            if (!soundToggleButton) return;
            if (!soundEnabled) {
                soundToggleButton.hidden = true;
                return;
            }

            soundToggleButton.textContent = state.soundMuted ? '🔇' : '🔊';
            soundToggleButton.classList.toggle('muted', state.soundMuted);
            soundToggleButton.setAttribute('aria-pressed', state.soundMuted ? 'true' : 'false');
            soundToggleButton.title = state.soundMuted ? 'تشغيل صوت الدردشة' : ('كتم صوت الدردشة - مستوى الصوت ' + Math.round(state.soundVolume * 100) + '%');
        }

        function unlockAudio() {
            if (!soundEnabled || state.audioUnlocked) return;
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;

            try {
                state.audioContext = state.audioContext || new AudioContextClass();
                if (state.audioContext.state === 'suspended') {
                    state.audioContext.resume().catch(function () {});
                }
                state.audioUnlocked = true;
            } catch (error) {
                state.audioUnlocked = false;
            }
        }

        function playNotificationSound() {
            if (!soundEnabled || state.soundMuted || state.soundVolume <= 0) return;
            unlockAudio();
            if (!state.audioContext || !state.audioUnlocked) return;

            try {
                const context = state.audioContext;
                const masterGain = context.createGain();
                const now = context.currentTime;
                const peakVolume = Math.max(0.02, Math.min(1, state.soundVolume));
                const peakGain = 0.75 * peakVolume;

                // v36: controllable and clearer alert tone. Volume comes from system settings.
                masterGain.gain.setValueAtTime(0.0001, now);
                masterGain.gain.exponentialRampToValueAtTime(Math.max(0.0001, peakGain), now + 0.018);
                masterGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.58);
                masterGain.connect(context.destination);

                function tone(startOffset, startFrequency, endFrequency, duration, type) {
                    const oscillator = context.createOscillator();
                    const gain = context.createGain();
                    const start = now + startOffset;

                    oscillator.type = type || 'triangle';
                    oscillator.frequency.setValueAtTime(startFrequency, start);
                    oscillator.frequency.exponentialRampToValueAtTime(endFrequency, start + duration);

                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(1.0, start + 0.014);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

                    oscillator.connect(gain);
                    gain.connect(masterGain);
                    oscillator.start(start);
                    oscillator.stop(start + duration + 0.03);
                }

                tone(0, 1040, 1320, 0.16, 'triangle');
                tone(0.17, 820, 1100, 0.17, 'triangle');
                tone(0.36, 1220, 1460, 0.12, 'sine');
            } catch (error) {
                // Keep chat silent if the browser blocks audio.
            }
        }

        function setSoundMuted(muted) {
            state.soundMuted = !!muted;
            window.localStorage.setItem(soundStorageKey, state.soundMuted ? '1' : '0');
            if (!state.soundMuted) unlockAudio();
            updateSoundButton();
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
                text.className = 'internal-chat-user-text';
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

        function messageSortKey(message) {
            const timestamp = parseInt(message?.created_at_timestamp || '0', 10);
            const id = parseInt(message?.id || '0', 10);
            return { timestamp: Number.isFinite(timestamp) ? timestamp : 0, id: Number.isFinite(id) ? id : 0 };
        }

        function todayIso(offsetDays) {
            const d = new Date();
            d.setDate(d.getDate() + (offsetDays || 0));
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function friendlyDateLabel(dateLabel) {
            const value = String(dateLabel || '').trim();
            if (!value) return '';
            if (value === todayIso(0)) return 'اليوم';
            if (value === todayIso(-1)) return 'أمس';
            return value;
        }

        function createMessageElement(message) {
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
            if (message.full_time || message.created_at) {
                meta.title = message.full_time || message.created_at;
            }

            bubble.appendChild(body);
            bubble.appendChild(meta);
            wrapper.appendChild(bubble);
            return wrapper;
        }

        function renderConversationMessages() {
            if (!messagesBox) return;

            const messages = Array.from(state.messages.values()).sort(function (a, b) {
                const ak = messageSortKey(a);
                const bk = messageSortKey(b);
                if (ak.timestamp !== bk.timestamp) return ak.timestamp - bk.timestamp;
                return ak.id - bk.id;
            });

            messagesBox.innerHTML = '';

            if (!messages.length) {
                const empty = document.createElement('div');
                empty.className = 'internal-chat-empty internal-chat-empty-large';
                empty.textContent = 'لا توجد رسائل بعد. ابدأ المحادثة برسالة قصيرة.';
                messagesBox.appendChild(empty);
                state.lastMessageId = 0;
                return;
            }

            let lastDate = null;
            let maxId = 0;

            messages.forEach(function (message) {
                const dateLabel = String(message.date_label || '').trim();
                if (dateLabel && dateLabel !== lastDate) {
                    const separator = document.createElement('div');
                    separator.className = 'internal-chat-date-separator';
                    const span = document.createElement('span');
                    span.textContent = friendlyDateLabel(dateLabel);
                    separator.appendChild(span);
                    messagesBox.appendChild(separator);
                    lastDate = dateLabel;
                }

                messagesBox.appendChild(createMessageElement(message));
                maxId = Math.max(maxId, parseInt(message.id || '0', 10));
            });

            state.lastMessageId = maxId;
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }

        function upsertMessages(messages, reset) {
            if (!messagesBox) return;
            if (reset) {
                state.messages.clear();
                state.lastMessageId = 0;
            }

            if (Array.isArray(messages)) {
                messages.forEach(function (message) {
                    if (message && message.id) {
                        state.messages.set(String(message.id), message);
                    }
                });
            }

            renderConversationMessages();
        }

        function renderMessages(messages, reset) {
            upsertMessages(messages, reset);
        }

        async function loadBootstrap() {
            if (!ready) {
                showAlert('جدول الدردشة غير موجود بعد. نفّذ php artisan migrate ثم حدّث الصفحة.');
                return;
            }

            try {
                const data = await requestJson(urls.bootstrap);
                showAlert('');
                state.lastUnreadTotal = parseInt(data.unread_total || 0, 10);
                setUnreadTotal(state.lastUnreadTotal);
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
                state.lastUnreadTotal = parseInt(data.unread_total || 0, 10);
                setUnreadTotal(state.lastUnreadTotal);
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
                renderMessages([data.message], false);
                state.lastUnreadTotal = parseInt(data.unread_total || 0, 10);
                setUnreadTotal(state.lastUnreadTotal);
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
            const shouldFetchActiveConversation = state.open && !!state.activeUserId;

            // v35: do not poll the active conversation while the chat panel is closed.
            // Otherwise incoming messages are marked as read immediately and the unread badge stays zero.
            if (shouldFetchActiveConversation) {
                params.set('with_user_id', String(state.activeUserId));
                params.set('after_id', String(state.lastMessageId || 0));
            }

            try {
                const data = await requestJson(urls.poll + (params.toString() ? ('?' + params.toString()) : ''));
                const nextUnreadTotal = parseInt(data.unread_total || 0, 10);
                const previousUnreadTotal = state.lastUnreadTotal;
                const incomingMessages = shouldFetchActiveConversation && Array.isArray(data.messages)
                    ? data.messages.some(function (message) { return message && message.direction === 'incoming'; })
                    : false;

                setUnreadTotal(nextUnreadTotal);
                updateUsers(data.users || []);
                if (shouldFetchActiveConversation && Array.isArray(data.messages) && data.messages.length) {
                    renderMessages(data.messages, false);
                }

                if ((previousUnreadTotal !== null && nextUnreadTotal > previousUnreadTotal) || incomingMessages) {
                    playNotificationSound();
                }
                state.lastUnreadTotal = nextUnreadTotal;
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
            updateSoundButton();
            loadBootstrap();
            if (state.activeUserId) {
                window.setTimeout(poll, 150);
            }
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
        if (soundToggleButton) {
            soundToggleButton.addEventListener('click', function () {
                setSoundMuted(!state.soundMuted);
            });
        }
        ['click', 'keydown', 'pointerdown', 'touchstart'].forEach(function (eventName) {
            document.addEventListener(eventName, unlockAudio, { once: true, passive: true });
        });
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

        updateSoundButton();
        loadBootstrap();
        state.pollTimer = window.setInterval(poll, pollSeconds * 1000);
    });
})();
