<?php

namespace App\Http\Controllers;

use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    protected string $table = 'system_notifications';

    public function index(Request $request): View
    {
        if (class_exists(SystemNotificationService::class) && method_exists(SystemNotificationService::class, 'syncForCurrentUser')) {
            try {
                SystemNotificationService::syncForCurrentUser($request->user());
            } catch (\Throwable $e) {
                // لا نكسر صفحة الإشعارات إذا فشل توليد التنبيهات التلقائية.
            }
        }

        if (!Schema::hasTable($this->table)) {
            return view('notifications.index', [
                'notifications' => collect(),
                'unreadCount' => 0,
                'readCount' => 0,
                'hiddenCount' => 0,
                'totalCount' => 0,
            ]);
        }

        $query = $this->baseQuery(false)->orderByDesc($this->createdAtColumn());

        $notifications = $query->paginate(20)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $this->baseQuery(false)->whereNull($this->readColumn())->count(),
            'readCount' => $this->baseQuery(false)->whereNotNull($this->readColumn())->count(),
            'hiddenCount' => $this->baseQuery(true)->count(),
            'totalCount' => $this->baseQuery(false)->count(),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        if (Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'read_at')) {
                $updates['read_at'] = now();
            }
            if (Schema::hasColumn($this->table, 'is_read')) {
                $updates['is_read'] = 1;
            }
            if (!empty($updates)) {
                $this->baseQuery(false)->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم تعليم جميع الإشعارات كمقروءة.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        return $this->readAll($request);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        return $this->readAll($request);
    }

    public function read(Request $request, $notification): RedirectResponse
    {
        $id = $this->extractId($notification);
        if ($id && Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'read_at')) {
                $updates['read_at'] = now();
            }
            if (Schema::hasColumn($this->table, 'is_read')) {
                $updates['is_read'] = 1;
            }
            if (!empty($updates)) {
                $this->baseQuery(null)->where('id', $id)->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم تعليم الإشعار كمقروء.');
    }

    public function markAsRead(Request $request, $notification): RedirectResponse
    {
        return $this->read($request, $notification);
    }

    public function hideRead(Request $request): RedirectResponse
    {
        if (Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'is_hidden')) {
                $updates['is_hidden'] = 1;
            }
            if (Schema::hasColumn($this->table, 'hidden_at')) {
                $updates['hidden_at'] = now();
            }

            if (!empty($updates)) {
                $this->baseQuery(false)->whereNotNull($this->readColumn())->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم إخفاء الإشعارات المقروءة.');
    }

    public function hide(Request $request, $notification): RedirectResponse
    {
        $id = $this->extractId($notification);
        if ($id && Schema::hasTable($this->table)) {
            $updates = [];
            if (Schema::hasColumn($this->table, 'is_hidden')) {
                $updates['is_hidden'] = 1;
            }
            if (Schema::hasColumn($this->table, 'hidden_at')) {
                $updates['hidden_at'] = now();
            }
            if (!empty($updates)) {
                $this->baseQuery(null)->where('id', $id)->update($updates);
            }
        }

        return redirect()->route('notifications.index')->with('success', 'تم إخفاء الإشعار.');
    }

    public function deleteHidden(Request $request): RedirectResponse
    {
        if (Schema::hasTable($this->table)) {
            $this->baseQuery(true)->delete();
        }

        return redirect()->route('notifications.index')->with('success', 'تم حذف الإشعارات المخفية نهائياً.');
    }

    public function purgeHidden(Request $request): RedirectResponse
    {
        return $this->deleteHidden($request);
    }

    public function clearHidden(Request $request): RedirectResponse
    {
        return $this->deleteHidden($request);
    }

    public function destroy(Request $request, $notification): RedirectResponse
    {
        $id = $this->extractId($notification);
        if ($id && Schema::hasTable($this->table)) {
            $this->baseQuery(null)->where('id', $id)->delete();
        }

        return redirect()->route('notifications.index')->with('success', 'تم حذف الإشعار.');
    }

    protected function baseQuery(?bool $hiddenOnly = false)
    {
        $query = DB::table($this->table);

        if (Schema::hasColumn($this->table, 'user_id')) {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->whereNull('user_id');
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            });
        }

        if ($hiddenOnly === true) {
            $query->where(function ($q) {
                if (Schema::hasColumn($this->table, 'is_hidden')) {
                    $q->orWhere('is_hidden', 1)->orWhere('is_hidden', true);
                }
                if (Schema::hasColumn($this->table, 'hidden_at')) {
                    $q->orWhereNotNull('hidden_at');
                }
            });
        } elseif ($hiddenOnly === false) {
            $query->where(function ($q) {
                if (Schema::hasColumn($this->table, 'is_hidden')) {
                    $q->whereNull('is_hidden')->orWhere('is_hidden', 0)->orWhere('is_hidden', false);
                }
                if (Schema::hasColumn($this->table, 'hidden_at')) {
                    $q->whereNull('hidden_at');
                }
            });
        }

        return $query;
    }

    protected function readColumn(): string
    {
        return Schema::hasColumn($this->table, 'read_at') ? 'read_at' : 'created_at';
    }

    protected function createdAtColumn(): string
    {
        return Schema::hasColumn($this->table, 'created_at') ? 'created_at' : 'id';
    }

    protected function extractId($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_object($value) && isset($value->id) && is_numeric($value->id)) {
            return (int) $value->id;
        }
        if (is_array($value) && isset($value['id']) && is_numeric($value['id'])) {
            return (int) $value['id'];
        }
        return null;
    }
}