@php
    $daInternalChatEnabled = false;
    $daInternalChatReady = false;

    try {
        $daInternalChatEnabled = (string) \App\Models\Setting::getValue('internal_chat_enabled', '1') === '1';
        $daInternalChatReady = \Illuminate\Support\Facades\Schema::hasTable('internal_chat_messages');
        $daInternalChatSoundEnabled = (string) \App\Models\Setting::getValue('internal_chat_sound_enabled', '1') === '1';
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
        data-send-url="{{ route('internal-chat.send') }}"
        data-read-url-template="{{ route('internal-chat.read', ['user' => '__USER__']) }}"
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
                    <small>تواصل سريع بين مستخدمي النظام</small>
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
                    <input type="search" class="internal-chat-search" data-chat-user-search placeholder="ابحث عن مستخدم...">
                    <div class="internal-chat-users-list" data-chat-users>
                        <div class="internal-chat-empty">جاري تحميل المستخدمين...</div>
                    </div>
                </aside>

                <main class="internal-chat-conversation">
                    <div class="internal-chat-conversation-head" data-chat-conversation-head>
                        <div>
                            <strong data-chat-active-name>اختر مستخدمًا</strong>
                            <small data-chat-active-subtitle>لعرض المحادثة والرسائل</small>
                        </div>
                    </div>

                    <div class="internal-chat-messages" data-chat-messages>
                        <div class="internal-chat-empty internal-chat-empty-large">اختر مستخدمًا من القائمة لبدء محادثة سريعة.</div>
                    </div>

                    <form class="internal-chat-form" data-chat-form autocomplete="off">
                        <textarea data-chat-input rows="2" maxlength="2000" placeholder="اكتب رسالتك هنا..." @disabled(! auth()->user()?->hasPermission('internal_chat.send'))></textarea>
                        <button type="submit" @disabled(! auth()->user()?->hasPermission('internal_chat.send'))>إرسال</button>
                    </form>

                    <div class="internal-chat-note">
                        هذه الدردشة للتنسيق الداخلي السريع، ولا تعتبر اعتمادًا رسميًا للمستندات.
                    </div>
                </main>
            </div>
        </section>
    </div>
@endif
