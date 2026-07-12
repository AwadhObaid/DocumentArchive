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
            conversationMessagesTemplate: widget.dataset.conversationMessagesUrlTemplate,
            send: widget.dataset.sendUrl,
            readTemplate: widget.dataset.readUrlTemplate,
            groupStore: widget.dataset.groupStoreUrl,
            archived: widget.dataset.archivedUrl,
            archiveTemplate: widget.dataset.archiveUrlTemplate,
            restoreTemplate: widget.dataset.restoreUrlTemplate,
            deleteTemplate: widget.dataset.deleteUrlTemplate,
            search: widget.dataset.searchUrl,
            documentLookup: widget.dataset.documentLookupUrl,
            memoLookup: widget.dataset.memoLookupUrl,
            typing: widget.dataset.typingUrl
        };

        const launcher = widget.querySelector('[data-chat-open]');
        const panel = widget.querySelector('[data-chat-panel]');
        const closeButton = widget.querySelector('[data-chat-close]');
        const soundToggleButton = widget.querySelector('[data-chat-sound-toggle]');
        const totalBadge = widget.querySelector('[data-chat-total]');
        const alertBox = widget.querySelector('[data-chat-alert]');
        const usersBox = widget.querySelector('[data-chat-users]');
        const searchInput = widget.querySelector('[data-chat-user-search]');
        const messageSearchInput = widget.querySelector('[data-chat-message-search]');
        const searchClearButton = widget.querySelector('[data-chat-search-clear]');
        const messagesBox = widget.querySelector('[data-chat-messages]');
        const typingIndicator = widget.querySelector('[data-chat-typing-indicator]');
        const activeName = widget.querySelector('[data-chat-active-name]');
        const activeSubtitle = widget.querySelector('[data-chat-active-subtitle]');
        const archiveButton = widget.querySelector('[data-chat-archive]');
        const restoreButton = widget.querySelector('[data-chat-restore]');
        const deleteButton = widget.querySelector('[data-chat-delete]');
        const archivedToggleButton = widget.querySelector('[data-chat-archives-toggle]');
        const groupToggleButton = widget.querySelector('[data-chat-group-toggle]');
        const groupForm = widget.querySelector('[data-chat-group-form]');
        const groupTitleInput = widget.querySelector('[data-chat-group-title]');
        const groupUsersBox = widget.querySelector('[data-chat-group-users]');
        const groupCancelButton = widget.querySelector('[data-chat-group-cancel]');
        const form = widget.querySelector('[data-chat-form]');
        const input = widget.querySelector('[data-chat-input]');
        const attachToggle = widget.querySelector('[data-chat-attach-toggle]');
        const attachmentPanel = widget.querySelector('[data-chat-attachment-panel]');
        const documentSearchInput = widget.querySelector('[data-chat-document-search]');
        const memoSearchInput = widget.querySelector('[data-chat-memo-search]');
        const documentLookupButton = widget.querySelector('[data-chat-document-lookup]');
        const memoLookupButton = widget.querySelector('[data-chat-memo-lookup]');
        const lookupResultsBox = widget.querySelector('[data-chat-lookup-results]');
        const selectedReferenceBox = widget.querySelector('[data-chat-selected-reference]');
        const clearReferenceButton = widget.querySelector('[data-chat-clear-reference]');

        const state = {
            open: false,
            users: [],
            conversations: [],
            archivedConversations: [],
            showArchived: false,
            activeArchived: false,
            activeKind: null,
            activeUserId: null,
            activeConversationId: null,
            activeName: '',
            lastMessageId: 0,
            loadingMessages: false,
            pollTimer: null,
            lastUnreadTotal: null,
            audioUnlocked: false,
            soundMuted: window.localStorage.getItem(soundStorageKey) === '1',
            audioContext: null,
            soundVolume: soundVolume,
            messages: new Map(),
            searchTimer: null,
            selectedReference: null,
            typingStopTimer: null,
            typingActive: false,
            lastTypingSignalAt: 0
        };

        function urlFor(template, value, token) {
            return (template || '').replace(token || '__USER__', encodeURIComponent(String(value)));
        }

        function conversationUrl(template, conversationId) {
            return urlFor(template, conversationId, '__CONVERSATION__');
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

        function updateTypingIndicator(typingUsers) {
            if (!typingIndicator) return;
            const users = Array.isArray(typingUsers) ? typingUsers.filter(Boolean) : [];
            if (!users.length) {
                typingIndicator.hidden = true;
                typingIndicator.textContent = '';
                return;
            }

            const names = users.map(function (user) {
                return String(user.name || 'مستخدم').trim();
            }).filter(Boolean).slice(0, 3);

            let text = '';
            if (names.length === 1) {
                text = names[0] + ' يكتب الآن...';
            } else if (names.length === 2) {
                text = names[0] + ' و' + names[1] + ' يكتبان الآن...';
            } else {
                text = names.join('، ') + ' يكتبون الآن...';
            }

            typingIndicator.hidden = false;
            typingIndicator.textContent = text;
        }

        async function sendTypingSignal(isTyping) {
            if (!urls.typing || !canSend || state.activeArchived) return;
            if (!state.activeConversationId && !state.activeUserId) return;

            const now = Date.now();
            if (isTyping && (now - state.lastTypingSignalAt) < 1800) return;
            if (isTyping) state.lastTypingSignalAt = now;

            const payload = { is_typing: !!isTyping };
            if (state.activeConversationId) payload.conversation_id = state.activeConversationId;
            else payload.receiver_id = state.activeUserId;

            try {
                await postJson(urls.typing, payload);
            } catch (error) {
                // Typing state is a soft UI hint; ignore failures silently.
            }
        }

        function queueTypingSignal() {
            if (!input || !canSend || state.activeArchived) return;
            const value = String(input.value || '').trim();
            if (!value) {
                clearTypingSignal(true);
                return;
            }

            state.typingActive = true;
            sendTypingSignal(true);

            window.clearTimeout(state.typingStopTimer);
            state.typingStopTimer = window.setTimeout(function () {
                clearTypingSignal(true);
            }, 4500);
        }

        function clearTypingSignal(force) {
            window.clearTimeout(state.typingStopTimer);
            state.typingStopTimer = null;
            if (force || state.typingActive) {
                state.typingActive = false;
                sendTypingSignal(false);
            }
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

        function threadKey(thread) {
            if (!thread) return '';
            if (thread.kind === 'group' || thread.kind === 'direct_conversation') {
                return 'conversation:' + thread.conversation_id;
            }
            return 'direct:' + thread.user_id;
        }

        function currentThreadKey() {
            if (state.activeConversationId) return 'conversation:' + state.activeConversationId;
            if (state.activeUserId) return 'direct:' + state.activeUserId;
            return '';
        }

        function mergedThreads() {
            const map = new Map();

            if (state.showArchived) {
                (state.archivedConversations || []).forEach(function (conversation) {
                    if (!conversation || conversation.deleted || !conversation.archived) return;
                    map.set('conversation:' + conversation.conversation_id, conversation);
                });
            } else {
                (state.conversations || []).forEach(function (conversation) {
                    if (!conversation || conversation.deleted || conversation.archived) return;
                    map.set('conversation:' + conversation.conversation_id, conversation);
                });

                (state.users || []).forEach(function (user) {
                    if (!user || user.archived || user.deleted) return;
                    const existingKey = user.conversation_id ? ('conversation:' + user.conversation_id) : null;
                    if (existingKey && map.has(existingKey)) return;
                    map.set('direct:' + user.user_id, user);
                });
            }

            return Array.from(map.values()).sort(function (a, b) {
                const au = parseInt(a.unread_count || 0, 10);
                const bu = parseInt(b.unread_count || 0, 10);
                if (au !== bu) return bu - au;
                const ai = parseInt(a.latest_message_id || 0, 10);
                const bi = parseInt(b.latest_message_id || 0, 10);
                return bi - ai;
            });
        }

        function renderUsers() {
            if (!usersBox) return;
            if (archivedToggleButton) {
                archivedToggleButton.classList.toggle('active', state.showArchived);
                archivedToggleButton.textContent = state.showArchived ? 'الرئيسية' : 'المؤرشفة';
                archivedToggleButton.title = state.showArchived ? 'العودة إلى المحادثات الرئيسية' : 'عرض المحادثات المؤرشفة';
            }

            const filter = String(searchInput?.value || '').trim().toLowerCase();
            const filtered = mergedThreads().filter(function (thread) {
                const haystack = ((thread.name || '') + ' ' + (thread.title || '') + ' ' + (thread.username || '') + ' ' + (thread.participants_label || '')).toLowerCase();
                return !filter || haystack.includes(filter);
            });

            usersBox.innerHTML = '';
            if (!filtered.length) {
                const empty = document.createElement('div');
                empty.className = 'internal-chat-empty';
                empty.textContent = state.showArchived ? 'لا توجد محادثات مؤرشفة مطابقة.' : 'لا توجد محادثات أو مستخدمون مطابقون.';
                usersBox.appendChild(empty);
                renderGroupUsers();
                return;
            }

            filtered.forEach(function (thread) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'internal-chat-user'
                    + (currentThreadKey() === threadKey(thread) ? ' active' : '')
                    + (thread.archived ? ' archived' : '');
                button.dataset.threadKind = thread.kind || 'direct';
                button.dataset.userId = thread.user_id || '';
                button.dataset.conversationId = thread.conversation_id || '';

                const avatar = document.createElement('span');
                avatar.className = 'internal-chat-avatar ' + ((thread.status === 'online') ? 'online' : (thread.kind === 'group' ? 'group' : 'offline'));
                avatar.textContent = (thread.kind === 'group') ? '👥' : initials(thread.name);

                const text = document.createElement('span');
                text.className = 'internal-chat-user-text';

                const name = document.createElement('span');
                name.className = 'internal-chat-user-name';
                name.textContent = thread.name || thread.title || ('مستخدم #' + (thread.user_id || thread.id));

                const preview = document.createElement('span');
                preview.className = 'internal-chat-user-preview';
                preview.textContent = (thread.archived ? '📦 مؤرشفة · ' : '') + (thread.latest_preview || thread.status_label || thread.role_name || 'مستخدم نشط');

                const status = document.createElement('span');
                status.className = 'internal-chat-user-status ' + ((thread.status === 'online') ? 'online' : (thread.kind === 'group' ? 'group' : 'offline'));
                status.textContent = thread.status_label || '';

                text.appendChild(name);
                text.appendChild(preview);
                text.appendChild(status);

                button.appendChild(avatar);
                button.appendChild(text);

                if (parseInt(thread.unread_count || 0, 10) > 0) {
                    const badge = document.createElement('span');
                    badge.className = 'internal-chat-user-unread';
                    badge.textContent = thread.unread_count > 99 ? '99+' : String(thread.unread_count);
                    button.appendChild(badge);
                } else {
                    const spacer = document.createElement('span');
                    button.appendChild(spacer);
                }

                button.addEventListener('click', function () {
                    selectThread(thread);
                });

                usersBox.appendChild(button);
            });

            renderGroupUsers();
        }

        function updatePayload(data) {
            if (Array.isArray(data.users)) state.users = data.users;
            if (Array.isArray(data.conversations)) state.conversations = data.conversations;
            if (Array.isArray(data.archived_conversations)) state.archivedConversations = data.archived_conversations;
            if (typeof data.unread_total !== 'undefined') {
                state.lastUnreadTotal = parseInt(data.unread_total || 0, 10);
                setUnreadTotal(state.lastUnreadTotal);
            }
            if (Array.isArray(data.typing_users)) updateTypingIndicator(data.typing_users);
            renderUsers();
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
            if (!dateLabel) return '';
            if (dateLabel === todayIso(0)) return 'اليوم';
            if (dateLabel === todayIso(-1)) return 'أمس';
            return dateLabel;
        }

        function createReferenceElement(type, reference) {
            if (!reference) return null;
            const element = document.createElement(reference.url ? 'a' : 'div');
            element.className = 'internal-chat-reference ' + type;
            element.textContent = (type === 'document' ? '📘 ' : '📝 ') + (reference.label || 'مرفق');
            if (reference.url) {
                element.href = reference.url;
                element.target = '_blank';
                element.rel = 'noopener';
            }
            return element;
        }

        function createMessageElement(message) {
            const wrapper = document.createElement('div');
            wrapper.className = 'internal-chat-message ' + (message.direction === 'outgoing' ? 'outgoing' : 'incoming');
            wrapper.dataset.messageId = message.id;

            const bubble = document.createElement('div');
            bubble.className = 'internal-chat-bubble';

            const body = document.createElement('div');
            body.textContent = message.body || '';
            if (!message.body && (message.document || message.memo)) {
                body.textContent = 'تم إرسال مرفق.';
            }

            bubble.appendChild(body);

            const documentReference = createReferenceElement('document', message.document);
            const memoReference = createReferenceElement('memo', message.memo);
            if (documentReference) bubble.appendChild(documentReference);
            if (memoReference) bubble.appendChild(memoReference);

            const meta = document.createElement('div');
            meta.className = 'internal-chat-meta';
            meta.textContent = (message.time || '') + (message.direction === 'outgoing' ? (message.read ? ' · تمت القراءة' : ' · مرسلة') : '');
            if (message.full_time || message.created_at) {
                meta.title = message.full_time || message.created_at;
            }

            bubble.appendChild(meta);
            wrapper.appendChild(bubble);
            return wrapper;
        }

        function renderConversationMessages(searchMode) {
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
                empty.textContent = searchMode ? 'لا توجد نتائج مطابقة داخل هذه المحادثة.' : 'لا توجد رسائل بعد. ابدأ المحادثة برسالة قصيرة.';
                messagesBox.appendChild(empty);
                if (!searchMode) state.lastMessageId = 0;
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

            if (!searchMode) {
                state.lastMessageId = maxId;
                messagesBox.scrollTop = messagesBox.scrollHeight;
            }
        }

        function renderMessages(messages, reset) {
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

            renderConversationMessages(false);
        }

        async function loadBootstrap() {
            if (!ready) {
                showAlert('تحديث الدردشة المتقدمة غير مكتمل. نفّذ php artisan migrate ثم حدّث الصفحة.');
                return;
            }

            try {
                const data = await requestJson(urls.bootstrap);
                showAlert('');
                updatePayload(data);
            } catch (error) {
                showAlert(error.message);
            }
        }

        function resetActiveSearch() {
            if (messageSearchInput) {
                messageSearchInput.value = '';
                messageSearchInput.hidden = !state.activeConversationId && !state.activeUserId;
            }
            if (searchClearButton) searchClearButton.hidden = true;
        }

        function setComposeDisabled(disabled) {
            const value = !!disabled;
            const submitButton = form ? form.querySelector('button[type="submit"]') : null;
            if (input) {
                input.disabled = value;
                input.placeholder = state.activeArchived
                    ? 'استعد المحادثة المؤرشفة قبل إرسال رسالة جديدة...'
                    : 'اكتب رسالتك هنا...';
            }
            if (submitButton) submitButton.disabled = value;
            if (attachToggle) attachToggle.disabled = value;
        }

        function updateHeaderActions() {
            const hasConversation = !!state.activeConversationId;
            if (restoreButton) restoreButton.hidden = !hasConversation || !state.activeArchived;
            if (archiveButton) archiveButton.hidden = !hasConversation || state.activeArchived;
            if (deleteButton) deleteButton.hidden = !hasConversation || state.activeArchived;
            if (messageSearchInput) messageSearchInput.hidden = !hasConversation && !state.activeUserId;
            setComposeDisabled(!canSend || state.activeArchived || (!state.activeConversationId && !state.activeUserId));
        }

        async function selectThread(thread) {
            if (!thread || state.loadingMessages) return;
            clearTypingSignal(true);
            updateTypingIndicator([]);
            state.loadingMessages = true;
            state.activeKind = thread.kind || 'direct';
            state.activeArchived = !!thread.archived;
            state.activeUserId = thread.user_id || null;
            state.activeConversationId = thread.conversation_id || null;
            state.activeName = thread.name || thread.title || '';
            renderUsers();
            resetActiveSearch();
            updateHeaderActions();

            if (activeName) activeName.textContent = state.activeName || 'محادثة';
            if (activeSubtitle) activeSubtitle.textContent = thread.status_label || thread.participants_label || 'محادثة محفوظة داخل النظام';
            updateHeaderActions();
            if (input && canSend && !state.activeArchived) {
                input.disabled = false;
                input.focus();
            }

            try {
                const loadUrl = state.activeConversationId
                    ? conversationUrl(urls.conversationMessagesTemplate, state.activeConversationId)
                    : urlFor(urls.messagesTemplate, state.activeUserId);
                const data = await requestJson(loadUrl);
                if (data.conversation && data.conversation.conversation_id) {
                    state.activeConversationId = data.conversation.conversation_id;
                }
                renderMessages(data.messages || [], true);
                updatePayload(data);
                updateHeaderActions();
                showAlert('');
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
            if (!state.activeUserId && !state.activeConversationId) {
                showAlert('اختر مستخدمًا أو مجموعة قبل إرسال الرسالة.');
                return;
            }
            const body = String(input?.value || '').trim();
            const reference = state.selectedReference;
            if (!body && !reference) return;
            clearTypingSignal(true);

            const submitButton = form?.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            const payload = { body: body };
            if (state.activeConversationId) payload.conversation_id = state.activeConversationId;
            else payload.receiver_id = state.activeUserId;
            if (reference && reference.type === 'document') payload.document_id = reference.id;
            if (reference && reference.type === 'memo') payload.memo_id = reference.id;

            try {
                const data = await postJson(urls.send, payload);
                input.value = '';
                clearSelectedReference();
                if (data.conversation && data.conversation.conversation_id) {
                    state.activeConversationId = data.conversation.conversation_id;
                }
                renderMessages([data.message], false);
                updatePayload(data);
                updateHeaderActions();
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
            const shouldFetchActiveConversation = state.open && (!!state.activeConversationId || !!state.activeUserId);

            if (shouldFetchActiveConversation) {
                if (state.activeConversationId) params.set('conversation_id', String(state.activeConversationId));
                else params.set('with_user_id', String(state.activeUserId));
                params.set('after_id', String(state.lastMessageId || 0));
            }

            try {
                const data = await requestJson(urls.poll + (params.toString() ? ('?' + params.toString()) : ''));
                const nextUnreadTotal = parseInt(data.unread_total || 0, 10);
                const previousUnreadTotal = state.lastUnreadTotal;
                const incomingMessages = shouldFetchActiveConversation && Array.isArray(data.messages)
                    ? data.messages.some(function (message) { return message && message.direction === 'incoming'; })
                    : false;

                updatePayload(data);
                if (shouldFetchActiveConversation && Array.isArray(data.typing_users)) updateTypingIndicator(data.typing_users);
                else if (!shouldFetchActiveConversation) updateTypingIndicator([]);
                if (shouldFetchActiveConversation && Array.isArray(data.messages) && data.messages.length) {
                    renderMessages(data.messages, false);
                }

                if ((previousUnreadTotal !== null && nextUnreadTotal > previousUnreadTotal) || incomingMessages) {
                    playNotificationSound();
                }
                state.lastUnreadTotal = nextUnreadTotal;
                showAlert('');
            } catch (error) {
                if (/صلاحية|غير مفعلة|migrate|جلسة|login|تحديث الدردشة/i.test(error.message)) {
                    showAlert(error.message);
                }
            }
        }

        async function searchMessages() {
            const q = String(messageSearchInput?.value || '').trim();
            if (!q) {
                renderConversationMessages(false);
                if (searchClearButton) searchClearButton.hidden = true;
                return;
            }
            if (q.length < 2 || (!state.activeConversationId && !state.activeUserId)) return;

            const params = new URLSearchParams();
            params.set('q', q);
            if (state.activeConversationId) params.set('conversation_id', String(state.activeConversationId));
            else params.set('user_id', String(state.activeUserId));

            try {
                const data = await requestJson(urls.search + '?' + params.toString());
                state.messages.clear();
                (data.messages || []).forEach(function (message) {
                    state.messages.set(String(message.id), message);
                });
                renderConversationMessages(true);
                if (searchClearButton) searchClearButton.hidden = false;
                showAlert('');
            } catch (error) {
                showAlert(error.message);
            }
        }

        function queueSearchMessages() {
            window.clearTimeout(state.searchTimer);
            state.searchTimer = window.setTimeout(searchMessages, 350);
        }

        async function reloadCurrentConversation() {
            if (state.activeConversationId) {
                const conv = { kind: 'group', conversation_id: state.activeConversationId, name: state.activeName };
                await selectThread(conv);
            } else if (state.activeUserId) {
                const direct = { kind: 'direct', user_id: state.activeUserId, name: state.activeName };
                await selectThread(direct);
            }
        }

        async function hideCurrentConversation(mode) {
            if (!state.activeConversationId) return;
            const question = mode === 'delete'
                ? 'سيتم مسح سجل الرسائل من شاشتك فقط، مع بقاء المستخدم/المجموعة في القائمة. لن تُحذف الرسائل من قاعدة البيانات نهائيًا. هل تريد المتابعة؟'
                : 'سيتم أرشفة المحادثة من قائمتك. هل تريد المتابعة؟';
            if (!window.confirm(question)) return;

            const targetUrl = mode === 'delete'
                ? conversationUrl(urls.deleteTemplate, state.activeConversationId)
                : conversationUrl(urls.archiveTemplate, state.activeConversationId);

            try {
                const data = await postJson(targetUrl, {});
                state.messages.clear();
                clearSelectedReference();
                updatePayload(data);

                if (mode === 'delete') {
                    renderConversationMessages(false);
                    updateHeaderActions();
                    showAlert(data.message || 'تم مسح سجل المحادثة ظاهريًا.');
                    return;
                }

                state.activeKind = null;
                state.activeArchived = false;
                state.activeUserId = null;
                state.activeConversationId = null;
                state.activeName = '';
                if (activeName) activeName.textContent = 'اختر محادثة';
                if (activeSubtitle) activeSubtitle.textContent = 'لعرض المحادثة والرسائل';
                updateHeaderActions();
                renderConversationMessages(false);
                showAlert(data.message || 'تم تنفيذ العملية.');
            } catch (error) {
                showAlert(error.message);
            }
        }

        async function loadArchivedConversations() {
            if (!urls.archived) return;
            try {
                const data = await requestJson(urls.archived);
                state.archivedConversations = Array.isArray(data.conversations) ? data.conversations : [];
                renderUsers();
                showAlert('');
            } catch (error) {
                showAlert(error.message);
            }
        }

        async function toggleArchivedView() {
            state.showArchived = !state.showArchived;
            if (searchInput) searchInput.value = '';
            if (state.showArchived) {
                await loadArchivedConversations();
            } else {
                renderUsers();
            }
        }

        async function restoreCurrentConversation() {
            if (!state.activeConversationId || !urls.restoreTemplate) return;
            const targetUrl = conversationUrl(urls.restoreTemplate, state.activeConversationId);

            try {
                const data = await postJson(targetUrl, {});
                const restored = data.conversation || {
                    kind: 'direct_conversation',
                    conversation_id: state.activeConversationId,
                    name: state.activeName
                };

                state.showArchived = false;
                state.activeArchived = false;
                if (Array.isArray(data.archived_conversations)) {
                    state.archivedConversations = data.archived_conversations;
                } else {
                    state.archivedConversations = (state.archivedConversations || []).filter(function (conversation) {
                        return String(conversation.conversation_id) !== String(state.activeConversationId);
                    });
                }

                updatePayload(data);
                showAlert(data.message || 'تمت استعادة المحادثة إلى القائمة الرئيسية.');
                await selectThread(restored);
            } catch (error) {
                showAlert(error.message);
            }
        }

        function renderGroupUsers() {
            if (!groupUsersBox) return;
            groupUsersBox.innerHTML = '';
            (state.users || []).forEach(function (user) {
                const label = document.createElement('label');
                label.className = 'internal-chat-group-user';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = user.user_id || user.id;
                const span = document.createElement('span');
                span.textContent = user.name || ('مستخدم #' + (user.user_id || user.id));
                label.appendChild(checkbox);
                label.appendChild(span);
                groupUsersBox.appendChild(label);
            });
        }

        function toggleGroupForm(force) {
            if (!groupForm) return;
            const shouldShow = typeof force === 'boolean' ? force : groupForm.hidden;
            groupForm.hidden = !shouldShow;
            if (shouldShow) {
                renderGroupUsers();
                if (groupTitleInput) groupTitleInput.focus();
            }
        }

        async function createGroup(event) {
            event.preventDefault();
            if (!groupForm) return;
            const selectedIds = Array.from(groupForm.querySelectorAll('input[type="checkbox"]:checked')).map(function (checkbox) {
                return parseInt(checkbox.value, 10);
            }).filter(Boolean);
            if (!selectedIds.length) {
                showAlert('اختر مستخدمًا واحدًا على الأقل للمجموعة.');
                return;
            }

            const submitButton = groupForm.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            try {
                const data = await postJson(urls.groupStore, {
                    title: String(groupTitleInput?.value || '').trim(),
                    user_ids: selectedIds
                });
                if (groupTitleInput) groupTitleInput.value = '';
                groupForm.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) { checkbox.checked = false; });
                toggleGroupForm(false);
                updatePayload(data);
                if (data.conversation) {
                    await selectThread(data.conversation);
                }
                showAlert('');
            } catch (error) {
                showAlert(error.message);
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        }

        async function lookupReference(type) {
            const isDocument = type === 'document';
            const inputEl = isDocument ? documentSearchInput : memoSearchInput;
            const url = isDocument ? urls.documentLookup : urls.memoLookup;
            const q = String(inputEl?.value || '').trim();
            if (!q || q.length < 2) {
                showLookupResults([], 'اكتب حرفين على الأقل للبحث.');
                return;
            }

            try {
                const data = await requestJson(url + '?q=' + encodeURIComponent(q));
                showLookupResults(data.items || [], '', type);
                showAlert('');
            } catch (error) {
                showAlert(error.message);
            }
        }

        function showLookupResults(items, emptyMessage, type) {
            if (!lookupResultsBox) return;
            lookupResultsBox.innerHTML = '';
            if (!Array.isArray(items) || !items.length) {
                const empty = document.createElement('div');
                empty.className = 'internal-chat-lookup-empty';
                empty.textContent = emptyMessage || 'لا توجد نتائج مطابقة.';
                lookupResultsBox.appendChild(empty);
                return;
            }

            items.forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'internal-chat-lookup-item';
                button.textContent = item.label || ('#' + item.id);
                button.addEventListener('click', function () {
                    setSelectedReference({ type: type, id: item.id, label: item.label });
                    if (attachmentPanel) attachmentPanel.hidden = true;
                });
                lookupResultsBox.appendChild(button);
            });
        }

        function setSelectedReference(reference) {
            state.selectedReference = reference || null;
            if (!selectedReferenceBox || !clearReferenceButton) return;
            if (!reference) {
                selectedReferenceBox.hidden = true;
                selectedReferenceBox.textContent = '';
                clearReferenceButton.hidden = true;
                return;
            }
            selectedReferenceBox.hidden = false;
            selectedReferenceBox.textContent = (reference.type === 'document' ? '📘 ' : '📝 ') + reference.label;
            clearReferenceButton.hidden = false;
        }

        function clearSelectedReference() {
            setSelectedReference(null);
            if (lookupResultsBox) lookupResultsBox.innerHTML = '';
            if (documentSearchInput) documentSearchInput.value = '';
            if (memoSearchInput) memoSearchInput.value = '';
        }

        function openPanel() {
            state.open = true;
            if (panel) panel.hidden = false;
            updateSoundButton();
            loadBootstrap();
            if (state.activeConversationId || state.activeUserId) {
                window.setTimeout(poll, 150);
            }
            if (searchInput) searchInput.focus();
        }

        function closePanel() {
            clearTypingSignal(true);
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
        if (messageSearchInput) messageSearchInput.addEventListener('input', queueSearchMessages);
        if (searchClearButton) {
            searchClearButton.addEventListener('click', function () {
                if (messageSearchInput) messageSearchInput.value = '';
                if (searchClearButton) searchClearButton.hidden = true;
                reloadCurrentConversation();
            });
        }
        if (form) form.addEventListener('submit', sendMessage);
        if (input) {
            input.addEventListener('input', queueTypingSignal);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form?.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }
        if (archivedToggleButton) archivedToggleButton.addEventListener('click', function () { toggleArchivedView(); });
        if (restoreButton) restoreButton.addEventListener('click', function () { restoreCurrentConversation(); });
        if (archiveButton) archiveButton.addEventListener('click', function () { hideCurrentConversation('archive'); });
        if (deleteButton) deleteButton.addEventListener('click', function () { hideCurrentConversation('delete'); });
        if (groupToggleButton) groupToggleButton.addEventListener('click', function () { toggleGroupForm(); });
        if (groupCancelButton) groupCancelButton.addEventListener('click', function () { toggleGroupForm(false); });
        if (groupForm) groupForm.addEventListener('submit', createGroup);
        if (attachToggle) attachToggle.addEventListener('click', function () {
            if (attachmentPanel) attachmentPanel.hidden = !attachmentPanel.hidden;
        });
        if (documentLookupButton) documentLookupButton.addEventListener('click', function () { lookupReference('document'); });
        if (memoLookupButton) memoLookupButton.addEventListener('click', function () { lookupReference('memo'); });
        if (documentSearchInput) {
            documentSearchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    lookupReference('document');
                }
            });
        }
        if (memoSearchInput) {
            memoSearchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    lookupReference('memo');
                }
            });
        }
        if (clearReferenceButton) clearReferenceButton.addEventListener('click', clearSelectedReference);

        updateSoundButton();
        updateHeaderActions();
        loadBootstrap();
        state.pollTimer = window.setInterval(poll, pollSeconds * 1000);
    });
})();
