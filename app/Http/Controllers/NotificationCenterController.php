<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        SystemNotificationService::syncForCurrentUser($request->user());

        $notifications = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->visible()
            ->latest()
            ->paginate(20);

        $unreadCount = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markAsRead(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return back()->with('success', 'تم تعليم الإشعار كمقروء.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('success', 'تم تعليم جميع الإشعارات كمقروءة.');
    }

    public function destroy(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update([
            'dismissed_at' => now(),
            'read_at' => $notification->read_at ?: now(),
        ]);

        return back()->with('success', 'تم إخفاء الإشعار.');
    }
}