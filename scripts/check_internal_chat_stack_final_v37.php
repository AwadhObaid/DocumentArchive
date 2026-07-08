<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$checks = [
    'routes/web.php' => [
        "use App\\Http\\Controllers\\InternalChatController;" => 'استدعاء InternalChatController',
        "Route::prefix('internal-chat')->name('internal-chat.')->group" => 'مجموعة Routes للدردشة الداخلية',
        "Route::get('/bootstrap', [InternalChatController::class, 'bootstrap'])->name('bootstrap')" => 'Route bootstrap',
        "Route::post('/messages', [InternalChatController::class, 'send'])->name('send')" => 'Route send',
    ],
    'app/Http/Middleware/ApplyRoutePermissions.php' => [
        "'internal-chat.bootstrap' => 'internal_chat.view'" => 'صلاحية عرض الدردشة',
        "'internal-chat.send' => 'internal_chat.send'" => 'صلاحية إرسال رسائل الدردشة',
    ],
    'app/Support/PermissionRegistry.php' => [
        "'key' => 'internal_chat'" => 'مجموعة صلاحيات الدردشة',
        "'internal_chat.view'" => 'مفتاح صلاحية العرض',
        "'internal_chat.send'" => 'مفتاح صلاحية الإرسال',
    ],
    'app/Http/Controllers/SettingsController.php' => [
        "'internal_chat_enabled' => '1'" => 'تفعيل الدردشة افتراضياً',
        "'internal_chat_poll_seconds' => '5'" => 'مدة التحديث الافتراضية',
        'internal_chat_sound_enabled' => 'تفعيل صوت الدردشة',
        'internal_chat_sound_volume' => 'إعداد مستوى صوت الدردشة',
        "'min:0'" => 'الحد الأدنى للصوت',
        "'max:100'" => 'الحد الأعلى للصوت',
    ],
    'resources/views/settings/edit.blade.php' => [
        'إعدادات الدردشة الداخلية العائمة' => 'قسم إعدادات الدردشة',
        'name="internal_chat_enabled"' => 'حقل تفعيل الدردشة',
        'name="internal_chat_poll_seconds"' => 'حقل مدة التحديث',
        'تفعيل التنبيه الصوتي عند وصول رسالة دردشة جديدة' => 'حقل تفعيل الصوت',
        'مستوى صوت تنبيه الدردشة' => 'عنوان مستوى الصوت',
        'internal_chat_sound_volume_number' => 'حقل رقم مستوى الصوت',
        'type="range"' => 'شريط مستوى الصوت',
    ],
    'resources/views/layouts/app.blade.php' => [
        'css/internal-chat.css' => 'تحميل CSS الدردشة',
        'partials.internal-chat-widget' => 'تضمين ويدجت الدردشة',
        'js/internal-chat.js' => 'تحميل JavaScript الدردشة',
    ],
    'resources/views/partials/internal-chat-widget.blade.php' => [
        'data-sound-enabled' => 'تمرير حالة الصوت',
        'data-sound-volume' => 'تمرير مستوى الصوت',
        'data-chat-sound-toggle' => 'زر كتم/تشغيل الصوت',
    ],
    'app/Http/Controllers/InternalChatController.php' => [
        'created_at_timestamp' => 'طابع وقت الرسالة للترتيب',
        'date_label' => 'فاصل تاريخ الرسائل',
        'full_time' => 'وقت الرسالة الكامل',
    ],
    'public/js/internal-chat.js' => [
        'messages: new Map()' => 'تجميع الرسائل بدون تكرار',
        'messageSortKey' => 'ترتيب الرسائل زمنياً',
        'internal-chat-date-separator' => 'فاصل التاريخ في الرسائل',
        'renderConversationMessages' => 'إعادة رسم الرسائل مرتبة',
        'playNotificationSound' => 'دالة صوت التنبيه',
        'shouldFetchActiveConversation = state.open && !!state.activeUserId' => 'عدم قراءة المحادثة وهي مغلقة',
        'do not poll the active conversation while the chat panel is closed' => 'تعليق إصلاح عداد غير المقروء',
        'dataset.soundVolume' => 'قراءة مستوى الصوت من الواجهة',
        'peakGain = 0.75 * peakVolume' => 'تطبيق مستوى الصوت على التنبيه',
        'internal-chat-user-text' => 'فصل اسم المستخدم عن آخر رسالة',
    ],
    'public/css/internal-chat.css' => [
        'left: 94px;' => 'موضع الدردشة على سطح المكتب',
        'left: 86px;' => 'موضع الدردشة على الجوال',
        'bottom: 82px;' => 'موضع نافذة الدردشة على الجوال',
        'z-index: 10000;' => 'طبقة ظهور الدردشة',
        'internal-chat-sound-toggle' => 'تنسيق زر الصوت',
        'internal-chat-date-separator' => 'تنسيق فاصل التاريخ',
        '.internal-chat-user-text' => 'تنسيق نص المستخدم',
        'minmax(0, 1fr)' => 'منع ضغط النص داخل قائمة المستخدمين',
    ],
];

$requiredFiles = [
    'app/Http/Controllers/InternalChatController.php',
    'app/Models/InternalChatMessage.php',
    'database/migrations/2026_07_07_140000_create_internal_chat_messages_table.php',
    'resources/views/partials/internal-chat-widget.blade.php',
    'public/css/internal-chat.css',
    'public/js/internal-chat.js',
];

$failed = false;

function project_path(string $root, string $relative): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

foreach ($requiredFiles as $file) {
    if (! is_file(project_path($root, $file))) {
        echo "[FAIL] Missing required file: {$file}\n";
        $failed = true;
    } else {
        echo "[OK] Required file exists: {$file}\n";
    }
}

foreach ($checks as $file => $needles) {
    $path = project_path($root, $file);
    if (! is_file($path)) {
        echo "[FAIL] Missing checked file: {$file}\n";
        $failed = true;
        continue;
    }

    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle => $label) {
        if (strpos($content, $needle) === false) {
            echo "[FAIL] {$file}: {$label}\n";
            $failed = true;
        } else {
            echo "[OK] {$file}: {$label}\n";
        }
    }
}

// Compatibility note for V35/V36 sound markers.
$jsPath = project_path($root, 'public/js/internal-chat.js');
$js = is_file($jsPath) ? (file_get_contents($jsPath) ?: '') : '';
$hasOldV35Tone = str_contains($js, 'tone(0, 880, 1040, 0.16)') && str_contains($js, 'tone(0.18, 660, 880, 0.18)');
$hasNewV36Tone = str_contains($js, "tone(0, 1040, 1320, 0.16, 'triangle')")
    && str_contains($js, "tone(0.17, 820, 1100, 0.17, 'triangle')")
    && str_contains($js, "tone(0.36, 1220, 1460, 0.12, 'sine')");

if (! $hasOldV35Tone && ! $hasNewV36Tone) {
    echo "[FAIL] public/js/internal-chat.js: نغمة تنبيه الدردشة غير مطابقة لـ V35 أو V36\n";
    $failed = true;
} elseif ($hasNewV36Tone) {
    echo "[OK] public/js/internal-chat.js: نغمة V36 الجديدة معتمدة\n";
} else {
    echo "[OK] public/js/internal-chat.js: نغمة V35 القديمة موجودة\n";
}

if ($failed) {
    echo "\nInternal chat final stack V37 check failed.\n";
    exit(1);
}

echo "\nInternal chat final stack V37 check passed.\n";
