<?php

namespace App\Services;

use App\Support\AttachmentIndexInventoryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttachmentIndexInventoryService
{
    private const SOURCE_CONFIG = [
        'document' => [
            'key' => 'documents',
            'attachment_table' => 'document_attachments',
            'attachment_alias' => 'a',
            'parent_table' => 'documents',
            'parent_alias' => 'r',
            'foreign_key' => 'document_id',
            'number_column' => 'reference_number',
            'title_expression' => "COALESCE(NULLIF(r.title, ''), NULLIF(r.subject, ''), CONCAT('كتاب ', r.reference_number))",
        ],
        'memo' => [
            'key' => 'memos',
            'attachment_table' => 'memo_attachments',
            'attachment_alias' => 'a',
            'parent_table' => 'memos',
            'parent_alias' => 'r',
            'foreign_key' => 'memo_id',
            'number_column' => 'memo_number',
            'title_expression' => "COALESCE(NULLIF(r.subject, ''), CONCAT('مذكرة ', r.memo_number))",
        ],
        'circular' => [
            'key' => 'circulars',
            'attachment_table' => 'circular_attachments',
            'attachment_alias' => 'a',
            'parent_table' => 'circulars',
            'parent_alias' => 'r',
            'foreign_key' => 'circular_id',
            'number_column' => 'circular_number',
            'title_expression' => "COALESCE(NULLIF(r.subject, ''), CONCAT('تعميم ', r.circular_number))",
        ],
        'misc_book' => [
            'key' => 'misc_books',
            'attachment_table' => 'misc_book_attachments',
            'attachment_alias' => 'a',
            'parent_table' => 'misc_books',
            'parent_alias' => 'r',
            'foreign_key' => 'misc_book_id',
            'number_column' => 'misc_number',
            'title_expression' => "COALESCE(NULLIF(r.subject, ''), CONCAT('كتاب متفرق ', r.misc_number))",
        ],
    ];

    public function paginate(array $sourceTypes, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->inventoryQuery($sourceTypes);
        $this->applyFilters($query, $filters);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $total = (clone $query)->count();

        $items = $query
            ->orderByRaw("CASE WHEN resolved_status = 'unindexed' THEN 0 WHEN resolved_status = 'needs_ocr' THEN 1 WHEN resolved_status IN ('failed', 'missing') THEN 2 ELSE 3 END")
            ->orderByDesc('attachment_created_at')
            ->orderByDesc('attachment_id')
            ->forPage($page, $perPage)
            ->get()
            ->map(static fn (object $row): AttachmentIndexInventoryItem => new AttachmentIndexInventoryItem($row));

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function selection(array $sourceTypes, array $filters, int $limit = 500): array
    {
        $limit = max(1, min(500, $limit));
        $query = $this->inventoryQuery($sourceTypes);
        $this->applyFilters($query, $filters);

        $hasIndexTable = Schema::hasTable('attachment_text_indexes');
        $query->whereRaw($this->pdfCondition('inventory'));
        $query->whereRaw($this->resolvedStatusExpression($hasIndexTable) . " <> 'indexed'");

        $total = (clone $query)->count();
        $items = $query
            ->orderByRaw("CASE WHEN resolved_status = 'unindexed' THEN 0 WHEN resolved_status = 'needs_ocr' THEN 1 WHEN resolved_status IN ('failed', 'missing') THEN 2 ELSE 3 END")
            ->orderByDesc('attachment_created_at')
            ->orderByDesc('attachment_id')
            ->limit($limit)
            ->get()
            ->map(static function (object $row): array {
                return [
                    'source_type' => (string) $row->source_type,
                    'attachment_id' => (int) $row->attachment_id,
                    'status' => (string) $row->resolved_status,
                    'label' => trim((string) ($row->original_name ?: $row->file_name ?: $row->record_number ?: $row->attachment_id)),
                    'record_number' => (string) ($row->record_number ?? ''),
                    'record_title' => (string) ($row->record_title ?? ''),
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'returned' => count($items),
            'limit' => $limit,
            'limited' => $total > $limit,
        ];
    }

    public function statistics(array $sourceTypes): array
    {
        $query = $this->inventoryQuery($sourceTypes);
        $row = DB::query()
            ->fromSub($query, 'coverage')
            ->selectRaw('COUNT(*) AS total_attachments')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_indexable = 1 THEN 1 ELSE 0 END), 0) AS eligible')
            ->selectRaw("COALESCE(SUM(CASE WHEN is_indexable = 1 AND resolved_status = 'indexed' AND index_text_length > 0 THEN 1 ELSE 0 END), 0) AS indexed")
            ->selectRaw("COALESCE(SUM(CASE WHEN is_indexable = 1 AND resolved_status = 'unindexed' THEN 1 ELSE 0 END), 0) AS unindexed")
            ->selectRaw("COALESCE(SUM(CASE WHEN is_indexable = 1 AND resolved_status = 'needs_ocr' THEN 1 ELSE 0 END), 0) AS needs_ocr")
            ->selectRaw("COALESCE(SUM(CASE WHEN is_indexable = 1 AND resolved_status = 'failed' THEN 1 ELSE 0 END), 0) AS failed")
            ->selectRaw("COALESCE(SUM(CASE WHEN is_indexable = 1 AND resolved_status = 'missing' THEN 1 ELSE 0 END), 0) AS missing")
            ->selectRaw("COALESCE(SUM(CASE WHEN is_indexable = 0 THEN 1 ELSE 0 END), 0) AS unsupported")
            ->first();

        $eligible = (int) ($row->eligible ?? 0);
        $indexed = (int) ($row->indexed ?? 0);

        return [
            'total' => (int) ($row->total_attachments ?? 0),
            'eligible' => $eligible,
            'indexed' => $indexed,
            'unindexed' => (int) ($row->unindexed ?? 0),
            'needs_ocr' => (int) ($row->needs_ocr ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'missing' => (int) ($row->missing ?? 0),
            'unsupported' => (int) ($row->unsupported ?? 0),
            'attention' => (int) ($row->needs_ocr ?? 0) + (int) ($row->failed ?? 0) + (int) ($row->missing ?? 0),
            'coverage_percent' => $eligible > 0 ? round(($indexed / $eligible) * 100, 1) : 100.0,
        ];
    }

    public function decoratePaginator(LengthAwarePaginatorContract $paginator, string $sourceType): void
    {
        if (! isset(self::SOURCE_CONFIG[$sourceType])) {
            return;
        }

        $ids = collect($paginator->items())
            ->pluck('id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $coverage = $this->recordCoverage($sourceType, $ids);

        foreach ($paginator->items() as $record) {
            $data = $coverage->get((int) $record->id, [
                'eligible' => 0,
                'indexed' => 0,
                'unindexed' => 0,
                'attention' => 0,
            ]);

            $record->setAttribute('pdf_index_eligible', $data['eligible']);
            $record->setAttribute('pdf_index_indexed', $data['indexed']);
            $record->setAttribute('pdf_index_unindexed', $data['unindexed']);
            $record->setAttribute('pdf_index_attention', $data['attention']);
        }
    }

    private function recordCoverage(string $sourceType, Collection $sourceIds): Collection
    {
        $config = self::SOURCE_CONFIG[$sourceType];
        $table = $config['attachment_table'];

        if (! Schema::hasTable($table)) {
            return collect();
        }
        $foreignKey = $config['foreign_key'];
        $pdfCondition = $this->pdfCondition('a');
        $hasIndexTable = Schema::hasTable('attachment_text_indexes');

        $query = DB::table($table . ' as a')
            ->whereIn('a.' . $foreignKey, $sourceIds->all())
            ->groupBy('a.' . $foreignKey)
            ->selectRaw('a.' . $foreignKey . ' AS source_id')
            ->selectRaw("SUM(CASE WHEN {$pdfCondition} THEN 1 ELSE 0 END) AS eligible");

        if ($hasIndexTable) {
            $query->leftJoin('attachment_text_indexes as ti', function ($join) use ($sourceType): void {
                $join->on('ti.attachment_id', '=', 'a.id')
                    ->where('ti.source_type', '=', $sourceType);
            });

            $query->selectRaw("SUM(CASE WHEN {$pdfCondition} AND ti.index_status = 'indexed' AND ti.text_length > 0 THEN 1 ELSE 0 END) AS indexed")
                ->selectRaw("SUM(CASE WHEN {$pdfCondition} AND ti.id IS NULL THEN 1 ELSE 0 END) AS unindexed")
                ->selectRaw("SUM(CASE WHEN {$pdfCondition} AND ti.index_status IN ('needs_ocr', 'failed', 'missing') THEN 1 ELSE 0 END) AS attention");
        } else {
            $query->selectRaw('0 AS indexed')
                ->selectRaw("SUM(CASE WHEN {$pdfCondition} THEN 1 ELSE 0 END) AS unindexed")
                ->selectRaw('0 AS attention');
        }

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('a.deleted_at');
        }

        return $query->get()->mapWithKeys(static function (object $row): array {
            return [(int) $row->source_id => [
                'eligible' => (int) $row->eligible,
                'indexed' => (int) $row->indexed,
                'unindexed' => (int) $row->unindexed,
                'attention' => (int) $row->attention,
            ]];
        });
    }

    private function inventoryQuery(array $sourceTypes): Builder
    {
        $sourceTypes = array_values(array_intersect(array_keys(self::SOURCE_CONFIG), $sourceTypes));
        $queries = [];

        foreach ($sourceTypes as $sourceType) {
            $query = $this->sourceQuery($sourceType);

            if ($query) {
                $queries[] = $query;
            }
        }

        if ($queries === []) {
            $queries[] = DB::query()
                ->selectRaw("NULL AS source_type, NULL AS source_key, NULL AS attachment_id, NULL AS source_id, NULL AS original_name, NULL AS file_name, NULL AS file_path, NULL AS disk, NULL AS extension, NULL AS mime_type, 0 AS file_size, NULL AS attachment_created_at, NULL AS record_number, NULL AS record_title")
                ->whereRaw('1 = 0');
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $pdfCondition = $this->pdfCondition('inventory');
        $hasIndexTable = Schema::hasTable('attachment_text_indexes');

        $query = DB::query()->fromSub($union, 'inventory');

        if ($hasIndexTable) {
            $query->leftJoin('attachment_text_indexes as ti', function ($join): void {
                $join->on('ti.source_type', '=', 'inventory.source_type')
                    ->on('ti.attachment_id', '=', 'inventory.attachment_id');
            });
        }

        $query->select('inventory.*');

        if ($hasIndexTable) {
            $query->addSelect([
                'ti.id as index_id',
                'ti.index_status as stored_index_status',
                'ti.extractor',
                'ti.needs_ocr',
                'ti.text_length as index_text_length',
                'ti.indexed_text',
                'ti.error_message',
                'ti.last_indexed_at',
            ]);
            $query->selectRaw("CASE WHEN NOT ({$pdfCondition}) THEN 'unsupported' WHEN ti.id IS NULL THEN 'unindexed' ELSE COALESCE(ti.index_status, 'pending') END AS resolved_status");
        } else {
            $query->selectRaw('NULL AS index_id, NULL AS stored_index_status, NULL AS extractor, 0 AS needs_ocr, 0 AS index_text_length, NULL AS indexed_text, NULL AS error_message, NULL AS last_indexed_at');
            $query->selectRaw("CASE WHEN NOT ({$pdfCondition}) THEN 'unsupported' ELSE 'unindexed' END AS resolved_status");
        }

        $query->selectRaw("CASE WHEN {$pdfCondition} THEN 1 ELSE 0 END AS is_indexable");

        return $query;
    }

    private function sourceQuery(string $sourceType): ?Builder
    {
        $config = self::SOURCE_CONFIG[$sourceType] ?? null;

        if (! $config
            || ! Schema::hasTable($config['attachment_table'])
            || ! Schema::hasTable($config['parent_table'])) {
            return null;
        }

        $attachmentTable = $config['attachment_table'];
        $parentTable = $config['parent_table'];
        $foreignKey = $config['foreign_key'];
        $sourceKey = $config['key'];
        $numberColumn = $config['number_column'];
        $titleExpression = $config['title_expression'];

        $query = DB::table($attachmentTable . ' as a')
            ->join($parentTable . ' as r', 'r.id', '=', 'a.' . $foreignKey)
            ->selectRaw("'{$sourceType}' AS source_type")
            ->selectRaw("'{$sourceKey}' AS source_key")
            ->addSelect([
                'a.id as attachment_id',
                'a.' . $foreignKey . ' as source_id',
                'a.original_name',
                'a.file_name',
                'a.file_path',
                'a.disk',
                'a.extension',
                'a.mime_type',
                'a.file_size',
                'a.created_at as attachment_created_at',
                'r.' . $numberColumn . ' as record_number',
            ])
            ->selectRaw($titleExpression . ' AS record_title');

        if (Schema::hasColumn($attachmentTable, 'deleted_at')) {
            $query->whereNull('a.deleted_at');
        }

        if (Schema::hasColumn($parentTable, 'deleted_at')) {
            $query->whereNull('r.deleted_at');
        }

        return $query;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $source = (string) ($filters['source_type'] ?? 'all');
        $status = (string) ($filters['status'] ?? 'all');
        $search = trim((string) ($filters['q'] ?? ''));

        if ($source !== 'all') {
            $query->where('inventory.source_type', $source);
        }

        if ($status !== 'all') {
            $query->whereRaw($this->resolvedStatusExpression(Schema::hasTable('attachment_text_indexes')) . ' = ?', [$status]);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('inventory.original_name', 'like', $like)
                    ->orWhere('inventory.file_name', 'like', $like)
                    ->orWhere('inventory.record_number', 'like', $like)
                    ->orWhere('inventory.record_title', 'like', $like);

                if (Schema::hasTable('attachment_text_indexes')) {
                    $builder->orWhere('ti.indexed_text', 'like', $like);
                }
            });
        }
    }

    private function resolvedStatusExpression(bool $hasIndexTable): string
    {
        $pdfCondition = $this->pdfCondition('inventory');

        if (! $hasIndexTable) {
            return "CASE WHEN NOT ({$pdfCondition}) THEN 'unsupported' ELSE 'unindexed' END";
        }

        return "CASE WHEN NOT ({$pdfCondition}) THEN 'unsupported' WHEN ti.id IS NULL THEN 'unindexed' ELSE COALESCE(ti.index_status, 'pending') END";
    }

    private function pdfCondition(string $alias): string
    {
        return "(LOWER(COALESCE({$alias}.extension, '')) = 'pdf' OR LOWER(COALESCE({$alias}.mime_type, '')) LIKE '%pdf%')";
    }
}
