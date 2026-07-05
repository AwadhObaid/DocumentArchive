<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Memo;
use App\Models\Setting;
use App\Models\SystemNotification;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LiteController extends Controller
{
    public function index(Request $request): View
    {
        $this->syncNotifications($request);

        $latestDocuments = Document::query()
            ->with(['department', 'documentType', 'bookSubject'])
            ->latest('id')
            ->limit(6)
            ->get();

        $latestMemos = Memo::query()
            ->with('department')
            ->latest('id')
            ->limit(6)
            ->get();

        return view('lite.index', [
            'latestDocuments' => $latestDocuments,
            'latestMemos' => $latestMemos,
            'stats' => [
                'documents' => Document::query()->count(),
                'memos' => Memo::query()->count(),
                'notifications' => $this->notificationsQuery(false)->count(),
                'unread_notifications' => $this->notificationsQuery(false)->whereNull($this->readColumn())->count(),
            ],
            'pollSeconds' => $this->pollSeconds(),
        ]);
    }

    public function documents(Request $request): View
    {
        $query = Document::query()
            ->with(['department', 'documentType', 'bookSubject', 'mainAttachment'])
            ->withCount('attachments')
            ->latest('id');

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('reference_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhere('sender', 'like', "%{$q}%")
                    ->orWhere('receiver', 'like', "%{$q}%")
                    ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('bookSubject', fn ($subject) => $subject->where('name', 'like', "%{$q}%"));
            });
        }

        return view('lite.documents', [
            'documents' => $query->paginate(10)->withQueryString(),
            'q' => $q,
            'pollSeconds' => $this->pollSeconds(),
        ]);
    }

    public function documentShow(Document $document): View
    {
        $document->load(['department', 'documentType', 'bookSubject', 'creator', 'attachments.uploader']);

        return view('lite.document-show', [
            'document' => $document,
            'pollSeconds' => $this->pollSeconds(),
        ]);
    }

    public function memos(Request $request): View
    {
        $query = Memo::query()
            ->with(['department', 'mainAttachment'])
            ->withCount('attachments')
            ->latest('id');

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('memo_number', 'like', "%{$q}%")
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhere('sender', 'like', "%{$q}%")
                    ->orWhere('receiver', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$q}%"));
            });
        }

        return view('lite.memos', [
            'memos' => $query->paginate(10)->withQueryString(),
            'q' => $q,
            'pollSeconds' => $this->pollSeconds(),
        ]);
    }

    public function memoShow(Memo $memo): View
    {
        $memo->load(['department', 'creator', 'attachments.uploader']);

        return view('lite.memo-show', [
            'memo' => $memo,
            'pollSeconds' => $this->pollSeconds(),
        ]);
    }

    public function notifications(Request $request): View
    {
        $this->syncNotifications($request);

        return view('lite.notifications', [
            'notifications' => $this->notificationsQuery(false)
                ->orderByDesc($this->createdAtColumn())
                ->paginate(15)
                ->withQueryString(),
            'unreadCount' => $this->notificationsQuery(false)->whereNull($this->readColumn())->count(),
            'pollSeconds' => $this->pollSeconds(),
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $this->syncNotifications($request);

        $latest = $this->notificationsQuery(false)
            ->orderByDesc($this->createdAtColumn())
            ->limit(6)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => (string) ($notification->title ?? 'إشعار'),
                'body' => (string) ($notification->body ?? $notification->message ?? ''),
                'link' => (string) ($notification->link ?? $notification->url ?? ''),
                'created_at' => (string) ($notification->{$this->createdAtColumn()} ?? ''),
            ]);

        return response()->json([
            'ok' => true,
            'unread_count' => $this->notificationsQuery(false)->whereNull($this->readColumn())->count(),
            'latest' => $latest,
        ]);
    }

    private function syncNotifications(Request $request): void
    {
        if (class_exists(SystemNotificationService::class) && method_exists(SystemNotificationService::class, 'syncForCurrentUser')) {
            try {
                SystemNotificationService::syncForCurrentUser($request->user());
            } catch (\Throwable) {
                // لا نكسر نسخة الهاتف إذا فشل توليد التنبيهات التلقائية.
            }
        }
    }

    private function notificationsQuery(?bool $hiddenOnly = false)
    {
        if (! Schema::hasTable('system_notifications')) {
            return SystemNotification::query()->whereRaw('1 = 0');
        }

        $query = SystemNotification::query();
        $userId = Auth::id();

        if (Schema::hasColumn('system_notifications', 'user_id')) {
            $query->where(function ($builder) use ($userId) {
                $builder->whereNull('user_id');
                if ($userId) {
                    $builder->orWhere('user_id', $userId);
                }
            });
        }

        if ($hiddenOnly === true) {
            $query->where(function ($builder) {
                if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                    $builder->orWhere('is_hidden', true);
                }
                if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                    $builder->orWhereNotNull('hidden_at');
                }
                if (Schema::hasColumn('system_notifications', 'dismissed_at')) {
                    $builder->orWhereNotNull('dismissed_at');
                }
            });
        } else {
            $query->where(function ($builder) {
                if (Schema::hasColumn('system_notifications', 'is_hidden')) {
                    $builder->whereNull('is_hidden')->orWhere('is_hidden', false);
                }
                if (Schema::hasColumn('system_notifications', 'hidden_at')) {
                    $builder->whereNull('hidden_at');
                }
                if (Schema::hasColumn('system_notifications', 'dismissed_at')) {
                    $builder->whereNull('dismissed_at');
                }
            });
        }

        return $query;
    }

    private function readColumn(): string
    {
        return Schema::hasColumn('system_notifications', 'read_at') ? 'read_at' : 'created_at';
    }

    private function createdAtColumn(): string
    {
        return Schema::hasColumn('system_notifications', 'created_at') ? 'created_at' : 'id';
    }

    private function pollSeconds(): int
    {
        $seconds = (int) Setting::getValue('lite_notification_poll_seconds', 30);

        return max(10, min(300, $seconds));
    }
}
