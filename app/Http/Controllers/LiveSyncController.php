<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LiveSyncController extends Controller
{
    private const CACHE_KEY = 'documentarchive:live-sync:v91:snapshot';

    public function status(Request $request): JsonResponse
    {
        try {
            $snapshot = Cache::remember(self::CACHE_KEY, now()->addSeconds(5), function (): array {
                return $this->buildSnapshot();
            });

            $resources = [];
            $permissionMap = [
                'documents' => 'documents.view',
                'memos' => 'memos.view',
                'circulars' => 'circulars.view',
                'misc_books' => 'misc_books.view',
            ];

            foreach ($permissionMap as $resource => $permission) {
                if ($this->userCan($request, $permission) && isset($snapshot['resources'][$resource])) {
                    $resources[$resource] = $snapshot['resources'][$resource];
                }
            }

            $dashboardCounts = [];
            if ($this->userCan($request, 'dashboard.view')) {
                $dashboardCounts = $snapshot['dashboard_counts'] ?? [];

                foreach ([
                    'memos_total' => 'memos.view',
                    'circulars_total' => 'circulars.view',
                    'misc_books_total' => 'misc_books.view',
                ] as $key => $permission) {
                    if (! $this->userCan($request, $permission)) {
                        unset($dashboardCounts[$key]);
                    }
                }
            }

            return response()
                ->json([
                    'ok' => true,
                    'server_time' => now()->toIso8601String(),
                    'poll_interval_ms' => 15000,
                    'resources' => $resources,
                    'dashboard_counts' => $dashboardCounts,
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        } catch (Throwable $e) {
            report($e);

            return response()
                ->json([
                    'ok' => false,
                    'message' => 'تعذر فحص تحديثات البيانات مؤقتًا.',
                ], 503)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }
    }

    private function buildSnapshot(): array
    {
        $documentsState = $this->resourceState('documents', 'document_attachments');
        $memosState = $this->resourceState('memos', 'memo_attachments');
        $circularsState = $this->resourceState('circulars', 'circular_attachments');
        $miscBooksState = $this->resourceState('misc_books', 'misc_book_attachments');

        return [
            'resources' => [
                'documents' => array_merge(['label' => 'الكتب'], $documentsState),
                'memos' => array_merge(['label' => 'المذكرات'], $memosState),
                'circulars' => array_merge(['label' => 'التعاميم'], $circularsState),
                'misc_books' => array_merge(['label' => 'المتفرقات'], $miscBooksState),
            ],
            'dashboard_counts' => $this->dashboardCounts(),
        ];
    }

    private function resourceState(string $table, ?string $attachmentTable = null): array
    {
        $main = $this->tableState($table);
        $attachments = $attachmentTable ? $this->tableState($attachmentTable) : null;

        return [
            'signature' => hash('sha256', json_encode([
                'main' => $main,
                'attachments' => $attachments,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'active' => $main['active'],
            'trashed' => $main['trashed'],
            'total' => $main['total'],
            'latest_change' => $this->latestTimestamp([
                $main['latest_change'],
                $attachments['latest_change'] ?? null,
            ]),
        ];
    }

    private function tableState(string $table): array
    {
        if (! $this->tableExists($table)) {
            return [
                'exists' => false,
                'total' => 0,
                'active' => 0,
                'trashed' => 0,
                'max_id' => 0,
                'latest_change' => null,
            ];
        }

        $hasDeletedAt = $this->columnExists($table, 'deleted_at');
        $hasUpdatedAt = $this->columnExists($table, 'updated_at');
        $hasCreatedAt = $this->columnExists($table, 'created_at');
        $hasId = $this->columnExists($table, 'id');

        $total = (int) DB::table($table)->count();
        $active = $hasDeletedAt
            ? (int) DB::table($table)->whereNull('deleted_at')->count()
            : $total;
        $trashed = $hasDeletedAt
            ? (int) DB::table($table)->whereNotNull('deleted_at')->count()
            : 0;

        $latestChange = null;
        if ($hasUpdatedAt) {
            $latestChange = DB::table($table)->max('updated_at');
        } elseif ($hasCreatedAt) {
            $latestChange = DB::table($table)->max('created_at');
        }

        return [
            'exists' => true,
            'total' => $total,
            'active' => $active,
            'trashed' => $trashed,
            'max_id' => $hasId ? (int) (DB::table($table)->max('id') ?? 0) : 0,
            'latest_change' => $latestChange ? (string) $latestChange : null,
        ];
    }

    private function dashboardCounts(): array
    {
        $counts = [
            'documents_total' => $this->countRows('documents'),
            'documents_active' => $this->countActiveRows('documents'),
            'documents_today' => $this->countDocumentsForPeriod('today'),
            'documents_month' => $this->countDocumentsForPeriod('month'),
            'attachments_total' => $this->countRows('document_attachments'),
            'documents_with_attachments' => $this->countDocumentsByAttachmentPresence(true),
            'documents_without_attachments' => $this->countDocumentsByAttachmentPresence(false),
            'documents_trashed' => $this->countTrashedRows('documents'),
            'memos_total' => $this->countActiveRows('memos'),
            'circulars_total' => $this->countActiveRows('circulars'),
            'misc_books_total' => $this->countActiveRows('misc_books'),
        ];

        return array_map(static fn ($value): int => (int) $value, $counts);
    }

    private function countRows(string $table): int
    {
        return $this->tableExists($table) ? (int) DB::table($table)->count() : 0;
    }

    private function countActiveRows(string $table): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($this->columnExists($table, 'deleted_at')) {
            $query->whereNull($table . '.deleted_at');
        }

        return (int) $query->count();
    }

    private function countTrashedRows(string $table): int
    {
        if (! $this->tableExists($table) || ! $this->columnExists($table, 'deleted_at')) {
            return 0;
        }

        return (int) DB::table($table)->whereNotNull('deleted_at')->count();
    }

    private function countDocumentsForPeriod(string $period): int
    {
        if (! $this->tableExists('documents')) {
            return 0;
        }

        $dateColumn = $this->columnExists('documents', 'reference_date')
            ? 'reference_date'
            : ($this->columnExists('documents', 'created_at') ? 'created_at' : null);

        $query = DB::table('documents');
        if ($this->columnExists('documents', 'deleted_at')) {
            $query->whereNull('documents.deleted_at');
        }

        if (! $dateColumn) {
            return (int) $query->count();
        }

        if ($period === 'today') {
            $query->whereDate('documents.' . $dateColumn, now()->toDateString());
        } elseif ($period === 'month') {
            $query->whereYear('documents.' . $dateColumn, (int) now()->year)
                ->whereMonth('documents.' . $dateColumn, (int) now()->month);
        }

        return (int) $query->count();
    }

    private function countDocumentsByAttachmentPresence(bool $hasAttachments): int
    {
        if (! $this->tableExists('documents')) {
            return 0;
        }

        $query = DB::table('documents');
        if ($this->columnExists('documents', 'deleted_at')) {
            $query->whereNull('documents.deleted_at');
        }

        if (! $this->tableExists('document_attachments')) {
            return $hasAttachments ? 0 : (int) $query->count();
        }

        $callback = function ($subQuery): void {
            $subQuery->select(DB::raw(1))
                ->from('document_attachments')
                ->whereColumn('document_attachments.document_id', 'documents.id');
        };

        $hasAttachments ? $query->whereExists($callback) : $query->whereNotExists($callback);

        return (int) $query->count();
    }

    private function latestTimestamp(array $values): ?string
    {
        $values = array_values(array_filter($values, static fn ($value) => is_string($value) && $value !== ''));

        if ($values === []) {
            return null;
        }

        rsort($values);

        return $values[0];
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return $this->tableExists($table) && Schema::hasColumn($table, $column);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function userCan(Request $request, string $permission): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (isset($user->role) && in_array((string) $user->role, [
            'admin',
            'administrator',
            'super_admin',
            'مدير النظام',
            'مدير',
        ], true)) {
            return true;
        }

        if (method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission($permission);
        }

        return false;
    }
}
