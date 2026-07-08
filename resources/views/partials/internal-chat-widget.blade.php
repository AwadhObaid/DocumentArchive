@php
    $daInternalChatEnabled = false;
    $daInternalChatReady = false;
    $daInternalChatSoundEnabled = true;

    try {
        $daInternalChatEnabled = (string) \App\Models\Setting::getValue('internal_chat_enabled', '1') === '1';
        $daInternalChatSoundEnabled = (string) \App\Models\Setting::getValue('internal_chat_sound_enabled', '1') === '1';
        $daInternalChatReady = \Illuminate\Support\Facades\Schema::hasTable('internal_chat_messages')
            && \Illuminate\Support\Facades\Schema::hasTable('internal_chat_conversations')
            && \Illuminate\Support\Facades\Schema::hasTable('internal_chat_participants')
            && \Illuminate\Support\Facades\Schema::hasColumn('internal_chat_messages', 'conversation_id')
            && \Illuminate\Support\Facades\Schema::hasColumn('internal_chat_messages', 'document_id')
            && \Illuminate\Support\Facades\Schema::hasColumn('internal_chat_messages', 'memo_id')
            && \Illuminate\Support\Facades\Schema::hasColumn('internal_chat_participants', 'cleared_at');
    } catch (\Throwable $exception) {
        $daInternalChatEnabled = false;
        $daInternalChatReady = false;
    }
@endphp

@if(auth()->check() && auth()->user()?->hasPermission('internal_chat.view') && $daInternalChatEnabled)
    <div
        class="internal-chat-widget"
        id="internalChatWidget"
        dir="rtl"
        data-ready="{{ $daInternalChatReady ? '1' : '0' }}"
        data-bootstrap-url="{{ route('internal-chat.bootstrap') }}"
        data-users-url="{{ route('internal-chat.users') }}"
        data-poll-url="{{ route('internal-chat.poll') }}"
        data-messages-url-template="{{ route('internal-chat.messages', ['user' => '__USER__']) }}"
        data-conversation-messages-url-template="{{ route('internal-chat.conversations.messages', ['conversation' => '__CONVERSATION__']) }}"
        data-send-url="{{ route('internal-chat.send') }}"
        data-read-url-template="{{ route('internal-chat.read', ['user' => '__USER__']) }}"
        data-group-store-url="{{ route('internal-chat.groups.store') }}"
        data-archived-url="{{ route('internal-chat.archived') }}"
        data-archive-url-template="{{ route('internal-chat.conversations.archive', ['conversation' => '__CONVERSATION__']) }}"
        data-restore-url-template="{{ route('internal-chat.conversations.restore', ['conversation' => '__CONVERSATION__']) }}"
        data-delete-url-template="{{ route('internal-chat.conversations.delete', ['conversation' => '__CONVERSATION__']) }}"
        data-search-url="{{ route('internal-chat.search') }}"
        data-document-lookup-url="{{ route('internal-chat.lookup.documents') }}"
        data-memo-lookup-url="{{ route('internal-chat.lookup.memos') }}"
        data-can-send="{{ auth()->user()?->hasPermission('internal_chat.send') ? '1' : '0' }}"
        data-poll-seconds="{{ max(3, min(120, (int) \App\Models\Setting::getValue('internal_chat_poll_seconds', 5))) }}"
        data-sound-enabled="{{ ($daInternalChatSoundEnabled ?? true) ? '1' : '0' }}"
        data-sound-volume="{{ max(0, min(100, (int) \App\Models\Setting::getValue('internal_chat_sound_volume', 85))) }}"
        aria-live="polite"
    >
        <button class="internal-chat-launcher" type="button" data-chat-open aria-label="فتح الدردشة الداخلية">
            <span class="internal-chat-launcher-icon">💬</span>
            <span class="internal-chat-launcher-text">الدردشة</span>
            <span class="internal-chat-badge" data-chat-total hidden>0</span>
        </button>

        <section class="internal-chat-panel" data-chat-panel hidden>
            <header class="internal-chat-header">
                <div>
                    <strong>الدردشة الداخلية</strong>
                    <small>رسائل مباشرة، مجموعات، بحث، وأرشفة محادثات</small>
                </div>
                <div class="internal-chat-header-actions">
                    @if($daInternalChatSoundEnabled ?? true)
                        <button type="button" class="internal-chat-sound-toggle" data-chat-sound-toggle aria-label="كتم أو تشغيل صوت الدردشة">🔊</button>
                    @endif
                    <button type="button" class="internal-chat-close" data-chat-close aria-label="إغلاق">×</button>
                </div>
            </header>

            <div class="internal-chat-alert" data-chat-alert hidden></div>

            <div class="internal-chat-body">
                <aside class="internal-chat-users">
                    <div class="internal-chat-sidebar-tools">
                        <input type="search" class="internal-chat-search" data-chat-user-search placeholder="ابحث عن محادثة أو مستخدم...">
                        <button type="button" class="internal-chat-archives-toggle" data-chat-archives-toggle>المؤرشفة</button>
                        @if(auth()->user()?->hasPermission('internal_chat.send'))
                            <button type="button" class="internal-chat-group-toggle" data-chat-group-toggle>+ مجموعة</button>
                        @endif
                    </div>

                    <form class="internal-chat-group-form" data-chat-group-form hidden autocomplete="off">
                        <input type="text" data-chat-group-title maxlength="120" placeholder="اسم المجموعة اختياري">
                        <div class="internal-chat-group-users" data-chat-group-users></div>
                        <div class="internal-chat-group-actions">
                            <button type="submit">إنشاء</button>
                            <button type="button" data-chat-group-cancel>إلغاء</button>
                        </div>
                    </form>

                    <div class="internal-chat-users-list" data-chat-users>
                        <div class="internal-chat-empty">جاري تحميل المحادثات...</div>
                    </div>
                </aside>

                <main class="internal-chat-conversation">
                    <div class="internal-chat-conversation-head" data-chat-conversation-head>
                        <div class="internal-chat-title-block">
                            <strong data-chat-active-name>اختر محادثة</strong>
                            <small data-chat-active-subtitle>لعرض المحادثة والرسائل</small>
                        </div>
                        <div class="internal-chat-conversation-actions">
                            <input type="search" class="internal-chat-message-search" data-chat-message-search placeholder="بحث داخل المحادثة..." hidden>
                            <button type="button" class="internal-chat-action-btn" data-chat-search-clear hidden>إلغاء البحث</button>
                            <button type="button" class="internal-chat-action-btn success" data-chat-restore hidden>استعادة</button>
                            <button type="button" class="internal-chat-action-btn" data-chat-archive hidden>أرشفة</button>
                            <button type="button" class="internal-chat-action-btn danger" data-chat-delete hidden>حذف ظاهري</button>
                        </div>
                    </div>

                    <div class="internal-chat-messages" data-chat-messages>
                        <div class="internal-chat-empty internal-chat-empty-large">اختر مستخدمًا أو مجموعة لبدء محادثة سريعة.</div>
                    </div>

                    <form class="internal-chat-form" data-chat-form autocomplete="off">
                        <div class="internal-chat-compose-tools">
                            <button type="button" data-chat-attach-toggle @disabled(! auth()->user()?->hasPermission('internal_chat.send'))>📎 إرفاق كتاب/مذكرة</button>
                            <span data-chat-selected-reference class="internal-chat-selected-reference" hidden></span>
                            <button type="button" data-chat-clear-reference class="internal-chat-clear-reference" hidden>إزالة</button>
                        </div>

                        <div class="internal-chat-attachment-panel" data-chat-attachment-panel hidden>
                            <div class="internal-chat-lookup-row">
                                <input type="search" data-chat-document-search placeholder="ابحث عن كتاب بالرقم أو الموضوع...">
                                <button type="button" data-chat-document-lookup>بحث كتاب</button>
                            </div>
                            <div class="internal-chat-lookup-row">
                                <input type="search" data-chat-memo-search placeholder="ابحث عن مذكرة بالرقم أو الموضوع...">
                                <button type="button" data-chat-memo-lookup>بحث مذكرة</button>
                            </div>
                            <div class="internal-chat-lookup-results" data-chat-lookup-results></div>
                        </div>

                        <div class="internal-chat-compose-row">
                            <textarea data-chat-input rows="2" maxlength="2000" placeholder="اكتب رسالتك هنا..." @disabled(! auth()->user()?->hasPermission('internal_chat.send'))></textarea>
                            <button type="submit" @disabled(! auth()->user()?->hasPermission('internal_chat.send'))>إرسال</button>
                        </div>
                    </form>

                    <div class="internal-chat-note">
                        الأرشفة والحذف الظاهري يطبقان على قائمتك فقط، ولا يحذفان الرسائل من قاعدة البيانات نهائيًا.
                    </div>
                </main>
            </div>
        </section>
    </div>
@endif
