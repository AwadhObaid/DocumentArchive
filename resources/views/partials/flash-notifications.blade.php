{{-- DocumentArchive popup notifications partial --}}
@php
    $flashNotifications = [];
    $flashMap = [
        'success' => ['type' => 'success', 'title' => 'تم
ت العم
لية بنجاح'],
        'status' => ['type' => 'success', 'title' => 'تم
ت العم
لية بنجاح'],
        'message' => ['type' => 'info', 'title' => 'م
علوم
ة'],
        'info' => ['type' => 'info', 'title' => 'م
علوم
ة'],
        'warning' => ['type' => 'warning', 'title' => 'تنبيه'],
        'error' => ['type' => 'error', 'title' => 'حدث خطأ'],
        'danger' => ['type' => 'error', 'title' => 'حدث خطأ'],
    ];

    foreach ($flashMap as $key => $meta) {
        if (session()->has($key)) {
            $value = session($key);
            if (is_array($value)) {
                $value = implode("\n", array_filter($value));
            }
            $flashNotifications[] = [
                'type' => $meta['type'],
                'title' => $meta['title'],
                'message' => (string) $value,
            ];
        }
    }

    if (isset($errors) && $errors->any()) {
        $messages = collect($errors->all())->take(5)->implode("\n");
        $remaining = max($errors->count() - 5, 0);
        if ($remaining > 0) {
            $messages .= "\n" . 'وتوجد ' . $remaining . ' م
لاحظات إضافية.';
        }
        $flashNotifications[] = [
            'type' => 'error',
            'title' => 'يرجى م
راجعة البيانات',
            'message' => $messages,
        ];
    }
@endphp

<div id="app-toast-container" class="app-toast-container" aria-live="polite" aria-atomic="true"></div>

@if (!empty($flashNotifications))
    <script>
        window.AppFlashNotifications = (window.AppFlashNotifications || []).concat(@json($flashNotifications, JSON_UNESCAPED_UNICODE));
    </script>
@endif