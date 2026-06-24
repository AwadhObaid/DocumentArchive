<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->normalizeFilters($request);

        $query = $this->baseDocumentsQuery($filters);

        $documents = (clone $query)
            ->latest($this->dateColumn())
            ->paginate(25)
            ->withQueryString();

        $statsQuery = $this->baseDocumentsQuery($filters);

        $stats = [
            'total_documents' => (clone $statsQuery)->count(),
            'deleted_documents' => $this->supportsSoftDeletes()
                ? (clone $this->baseDocumentsQuery($filters, true))->onlyTrashed()->count()
                : 0,
            'attachments_count' => class_exists(DocumentAttachment::class) ? DocumentAttachment::query()->count() : 0,
            'this_month' => $this->documentsThisMonthCount(),
        ];

        $byDepartment = $this->groupDocumentsBy('department_id', $filters);
        $byType = $this->groupDocumentsBy('document_type_id', $filters);

        $departments = class_exists(Department::class)
            ? Department::query()->orderBy('name')->get()
            : collect();

        $documentTypes = class_exists(DocumentType::class)
            ? DocumentType::query()->orderBy('name')->get()
            : collect();

        return view('reports.index', compact(
            'documents',
            'stats',
            'byDepartment',
            'byType',
            'departments',
            'documentTypes',
            'filters'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->normalizeFilters($request);
        $documents = $this->baseDocumentsQuery($filters)
            ->latest($this->dateColumn())
            ->get();

        $fileName = 'documents-report-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($documents) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Arabic support in Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'رقم الكتاب',
                'تاريخ الكتاب',
                'الموضوع',
                'الإدارة',
                'نوع الكتاب',
                'البوليصة الرئيسية',
                'البوليصة الفرعية',
                'تاريخ الإدخال',
            ]);

            foreach ($documents as $document) {
                fputcsv($handle, [
                    $document->reference_number ?? '',
                    $this->formatDateValue($document->reference_date ?? null),
                    $document->subject ?? '',
                    optional($document->department ?? null)->name ?? '',
                    optional($document->documentType ?? null)->name ?? '',
                    $document->main_policy_number ?? '',
                    $document->sub_policy_number ?? '',
                    $this->formatDateValue($document->created_at ?? null),
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function baseDocumentsQuery(array $filters, bool $includeDeletedForStats = false): Builder
    {
        $query = Document::query();

        if ($this->supportsSoftDeletes() && ($filters['include_deleted'] || $includeDeletedForStats)) {
            $query->withTrashed();
        }

        foreach (['department', 'documentType', 'attachments'] as $relation) {
            if (method_exists(Document::class, $relation)) {
                $query->with($relation);
            }
        }

        $dateColumn = $this->dateColumn();

        if ($filters['date_from']) {
            $query->whereDate($dateColumn, '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate($dateColumn, '<=', $filters['date_to']);
        }

        if ($filters['department_id'] && Schema::hasColumn('documents', 'department_id')) {
            $query->where('department_id', $filters['department_id']);
        }

        if ($filters['document_type_id'] && Schema::hasColumn('documents', 'document_type_id')) {
            $query->where('document_type_id', $filters['document_type_id']);
        }

        if ($filters['keyword']) {
            $keyword = trim($filters['keyword']);
            $searchableColumns = array_values(array_filter([
                Schema::hasColumn('documents', 'reference_number') ? 'reference_number' : null,
                Schema::hasColumn('documents', 'subject') ? 'subject' : null,
                Schema::hasColumn('documents', 'main_policy_number') ? 'main_policy_number' : null,
                Schema::hasColumn('documents', 'sub_policy_number') ? 'sub_policy_number' : null,
                Schema::hasColumn('documents', 'notes') ? 'notes' : null,
                Schema::hasColumn('documents', 'description') ? 'description' : null,
            ]));

            if (!empty($searchableColumns)) {
                $query->where(function (Builder $builder) use ($searchableColumns, $keyword) {
                    foreach ($searchableColumns as $column) {
                        $builder->orWhere($column, 'like', '%' . $keyword . '%');
                    }
                });
            }
        }

        return $query;
    }

    private function normalizeFilters(Request $request): array
    {
        return [
            'date_from' => $request->filled('date_from') ? $request->input('date_from') : null,
            'date_to' => $request->filled('date_to') ? $request->input('date_to') : null,
            'department_id' => $request->filled('department_id') ? $request->input('department_id') : null,
            'document_type_id' => $request->filled('document_type_id') ? $request->input('document_type_id') : null,
            'keyword' => $request->filled('keyword') ? $request->input('keyword') : null,
            'include_deleted' => $request->boolean('include_deleted'),
        ];
    }

    private function dateColumn(): string
    {
        return Schema::hasColumn('documents', 'reference_date') ? 'reference_date' : 'created_at';
    }

    private function supportsSoftDeletes(): bool
    {
        return Schema::hasColumn('documents', 'deleted_at');
    }

    private function documentsThisMonthCount(): int
    {
        $dateColumn = $this->dateColumn();

        return Document::query()
            ->whereYear($dateColumn, now()->year)
            ->whereMonth($dateColumn, now()->month)
            ->count();
    }

    private function groupDocumentsBy(string $column, array $filters): array
    {
        if (!Schema::hasColumn('documents', $column)) {
            return [];
        }

        $items = (clone $this->baseDocumentsQuery($filters))
            ->selectRaw($column . ', COUNT(*) as total')
            ->groupBy($column)
            ->pluck('total', $column)
            ->toArray();

        if ($column === 'department_id' && class_exists(Department::class)) {
            $names = Department::query()->whereIn('id', array_keys($items))->pluck('name', 'id');
        } elseif ($column === 'document_type_id' && class_exists(DocumentType::class)) {
            $names = DocumentType::query()->whereIn('id', array_keys($items))->pluck('name', 'id');
        } else {
            $names = collect();
        }

        $result = [];
        foreach ($items as $id => $total) {
            $result[] = [
                'name' => $names[$id] ?? 'غير محدد',
                'total' => $total,
            ];
        }

        return $result;
    }

    private function formatDateValue($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
