@extends('layouts.app')

@section('title', 'إعدادات الإشعارات')

@section('content')
<style>
    .notification-settings-page { direction: rtl; }
    .notification-settings-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
    .notification-settings-title h1 { margin: 0 0 6px; font-size: 1.55rem; }
    .notification-settings-title p { margin: 0; color: var(--muted-color, #64748b); }
    .notification-settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 16px; }
    .notification-settings-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #e5e7eb); border-radius: 18px; padding: 18px; box-shadow: 0 10px 24px rgba(15, 23, 42, .06); }
    .notification-settings-card h2 { margin: 0 0 14px; font-size: 1.08rem; }
    .notification-option { display: flex; align-items: flex-start; gap: 10px; padding: 12px 0; border-top: 1px dashed var(--border-color, #e5e7eb); cursor: pointer; }
    .notification-option:first-of-type { border-top: 0; }
    .notification-option input { margin-top: 4px; width: 18px; height: 18px; }
    .notification-option strong { display: block; margin-bottom: 4px; }
    .notification-option span { display: block; color: var(--muted-color, #64748b); font-size: .9rem; line-height: 1.6; }
    .notification-settings-actions { position: sticky; bottom: 16px; display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; background: color-mix(in srgb, var(--body-bg, #f8fafc) 78%, transparent); backdrop-filter: blur(8px); padding: 12px; border-radius: 16px; }
    .notification-settings-actions .btn, .notification-settings-actions button, .notification-settings-header .btn { border: 0; border-radius: 12px; padding: 10px 16px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .notification-settings-actions button { background: #2563eb; color: #fff; }
    .notification-settings-actions .btn-secondary, .notification-settings-header .btn-secondary { background: #e5e7eb; color: #111827; }
    html.dark .notification-settings-card, body.dark .notification-settings-card, [data-theme="dark"] .notification-settings-card { background: #111827; border-color: #374151; color: #e5e7eb; }
    html.dark .notification-settings-actions, body.dark .notification-settings-actions, [data-theme="dark"] .notification-settings-actions { background: rgba(17, 24, 39, .82); }
    html.dark .notification-settings-actions .btn-secondary, body.dark .notification-settings-actions .btn-secondary, [data-theme="dark"] .notification-settings-actions .btn-secondary,
    html.dark .notification-settings-header .btn-secondary, body.dark .notification-settings-header .btn-secondary, [data-theme="dark"] .notification-settings-header .btn-secondary { background: #374151; color: #e5e7eb; }
    @media print { .notification-settings-actions, .notification-settings-header .btn { display: none !important; } }
</style>

<div class="notification-settings-page">
    <div class="notification-settings-header">
        <div class="notification-settings-title">
            <h1>⚙️ إعدادات الإشعارات</h1>
            <p>حدد أنواع الإشعارات التي تريد أن ينشئها النظام ويحفظها داخل مركز الإشعارات.</p>
        </div>
        <a href="{{ route('notifications.index') }}" class="btn btn-secondary">🔔 مركز الإشعارات</a>
    </div>

    <form method="POST" action="{{ route('notification-settings.update') }}">
        @csrf

        <div class="notification-settings-grid">
            @foreach($types as $category => $items)
                <section class="notification-settings-card">
                    <h2>
                        @switch($category)
                            @case('system') تنبيهات النظام
 @break
                            @case('events') أحداث الكتب والمرفقات @break
                            @case('flash') رسائل العمليات @break
                            @default {{ $category }}
                        @endswitch
                    </h2>

                    @foreach($items as $key => $meta)
                        @php
                            $pref = $preferences->get($key);
                            $checked = $pref ? (bool) $pref->enabled : true;
                        @endphp

                        <label class="notification-option">
                            <input type="checkbox" name="enabled[{{ $key }}]" value="1" @checked($checked)>
                            <div>
                                <strong>{{ $meta['label'] ?? $key }}</strong>
                                <span>{{ $meta['description'] ?? 'إشعار داخل مركز الإشعارات.' }}</span>
                            </div>
                        </label>
                    @endforeach
                </section>
            @endforeach
        </div>

        <div class="notification-settings-actions">
            <a href="{{ route('notifications.index') }}" class="btn btn-secondary">رجوع</a>
            <button type="submit">💾 حفظ إعدادات الإشعارات</button>
        </div>
    </form>
</div>
@endsection