<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ReportPrintController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('documents')) {
            abort(500, 'جدول documents غير موجود. يرجى فحص قاعدة البيانات أولاً.');
        }

        $filters = $this->normalizeFilters($request);
        $query = $this->baseDocumentsQuery($filters);

        $documents = (clone $query)
            ->orderByDesc($this->dateColumn())
            ->orderByDesc('id')
            ->get();

        $documentIds = $documents->pluck('id')->filter()->values();

        $attachmentsCount = 0;
        if (Schema::hasTable('document_attachments') && $documentIds->isNotEmpty()) {
            $attachmentsCount = DocumentAttachment::query()
                ->whereIn('document_id', $documentIds)
                ->count();
        }

        $summary = [
            'total_documents' => $documents->count(),
            'attachments_count' => $attachmentsCount,
            'this_month' => $this->documentsThisMonthCount($filters),
            'deleted_documents' => $this->deletedDocumentsCount($filters),
        ];

        $byDepartment = $this->groupCollectionByRelationName($documents, 'department', 'غير محدد');
        $byType = $this->groupCollectionByRelationName($documents, 'documentType', 'غير محدد');

        $filterLabels = $this->filterLabels($filters);
        $generatedAt = now();
        $generatedBy = Auth::user()?->name
            ?? Auth::user()?->username
            ?? 'مستخدم النظام';

        $reportCode = 'RPT-DOC-' . now()->format('Ymd-His');

        return view('reports.print', compact(
            'documents',
            'summary',
            'byDepartment',
            'byType',
            'filters',
            'filterLabels',
            'generatedAt',
            'generatedBy',
            'reportCode'
        ));
    }

    private function baseDocumentsQuery(array $filters): Builder
    {
        $query = Document::query();

        if ($this->supportsSoftDeletes() && $filters['include_deleted']) {
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
            $keyword = trim((string) $filters['keyword']);
            $columns = array_values(array_filter([
                Schema::hasColumn('documents', 'reference_number') ? 'reference_number' : null,
                Schema::hasColumn('documents', 'subject') ? 'subject' : null,
                Schema::hasColumn('documents', 'title') ? 'title' : null,
                Schema::hasColumn('documents', 'main_policy_number') ? 'main_policy_number' : null,
                Schema::hasColumn('documents', 'sub_policy_number') ? 'sub_policy_number' : null,
                Schema::hasColumn('documents', 'sender') ? 'sender' : null,
                Schema::hasColumn('documents', 'recipient') ? 'recipient' : null,
                Schema::hasColumn('documents', 'notes') ? 'notes' : null,
                Schema::hasColumn('documents', 'description') ? 'description' : null,
            ]));

            if ($columns) {
                $query->where(function (Builder $builder) use ($columns, $keyword) {
                    foreach ($columns as $column) {
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
        return Schema::hasColumn('documents', 'deleted_at') && method_exists(Document::class, 'bootSoftDeletes');
    }

    private function documentsThisMonthCount(array $filters): int
    {
        $dateColumn = $this->dateColumn();

        return (clone $this->baseDocumentsQuery($filters))
            ->whereYear($dateColumn, now()->year)
            ->whereMonth($dateColumn, now()->month)
            ->count();
    }

    private function deletedDocumentsCount(array $filters): int
    {
        if (! $this->supportsSoftDeletes()) {
            return 0;
        }

        $query = Document::onlyTrashed();
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

        return $query->count();
    }

    private function groupCollectionByRelationName($documents, string $relation, string $emptyLabel): array
    {
        return $documents
            ->groupBy(function ($document) use ($relation, $emptyLabel) {
                return optional($document->{$relation} ?? null)->name ?: $emptyLabel;
            })
            ->map(function ($items, $name) {
                return [
                    'name' => $name,
                    'total' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function filterLabels(array $filters): array
    {
        $departmentName = 'الكل';
        if ($filters['department_id'] && class_exists(Department::class)) {
            $departmentName = Department::query()->whereKey($filters['department_id'])->value('name') ?: 'غير معروف';
        }

        $typeName = 'الكل';
        if ($filters['document_type_id'] && class_exists(DocumentType::class)) {
            $typeName = DocumentType::query()->whereKey($filters['document_type_id'])->value('name') ?: 'غير معروف';
        }

        return [
            'period' => ($filters['date_from'] ?: 'البداية') . ' إلى ' . ($filters['date_to'] ?: 'اليوم'),
            'department' => $departmentName,
            'document_type' => $typeName,
            'keyword' => $filters['keyword'] ?: 'لا يوجد',
            'include_deleted' => $filters['include_deleted'] ? 'نعم' : 'لا',
        ];
    }
}
