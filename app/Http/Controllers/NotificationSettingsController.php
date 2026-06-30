<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $this->ensureCanManage($request);

        $types = SystemNotificationService::availableNotificationTypes();
        $preferences = NotificationPreference::query()
            ->global()
            ->get()
            ->keyBy('key');

        return view('notifications.settings', [
            'types' => $types,
            'preferences' => $preferences,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $types = SystemNotificationService::availableNotificationTypes();
        $enabled = (array) $request->input('enabled', []);

        foreach ($types as $category => $items) {
            foreach ($items as $key => $meta) {
                $preference = NotificationPreference::query()
                    ->whereNull('user_id')
                    ->where('key', $key)
                    ->first();

                if (! $preference) {
                    $preference = new NotificationPreference();
                    $preference->user_id = null;
                    $preference->key = $key;
                }

                $preference->label = $meta['label'] ?? $key;
                $preference->category = $category;
                $preference->enabled = array_key_exists($key, $enabled);
                $preference->applies_to = 'global';
                $preference->save();
            }
        }

        return back()->with('success', 'تم حفظ إعدادات الإشعارات بنجاح.');
    }

    private function ensureCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user, 403);

        $role = (string) ($user->role ?? '');
        $allowed = $role === 'admin'
            || str_contains($role, 'مدير')
            || (method_exists($user, 'hasPermission') && (
                $user->hasPermission('settings.manage')
                || $user->hasPermission('notifications.manage')
            ));

        abort_unless($allowed, 403, 'ليست لديك صلاحية إدارة إعدادات الإشعارات.');
    }
}