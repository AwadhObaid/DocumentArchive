<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class DataQualityPrintController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('documents')) {
            abort(500, 'جدول documents غير موجود. يرجى فحص قاعدة البيانات أولاً.');
        }

        $generatedAt = now();
        $generatedBy = Auth::user()?->name ?? Auth::user()?->username ?? 'مستخدم النظام';

        $documentsBase = DB::table('documents');
        if (Schema::hasColumn('documents', 'deleted_at')) {
            $documentsBase->whereNull('deleted_at');
        }

        $activeDocumentsCount = (clone $documentsBase)->count();

        $withoutAttachments = collect();
        if (Schema::hasTable('document_attachments')) {
            $withoutAttachments = (clone $documentsBase)
                ->leftJoin('document_attachments', 'documents.id', '=', 'document_attachments.document_id')
                ->whereNull('document_attachments.id')
                ->select($this->documentSelectColumns())
                ->orderByDesc('documents.reference_date')
                ->orderByDesc('documents.id')
                ->get();
        }

        $trashedDocuments = collect();
        if (Schema::hasColumn('documents', 'deleted_at')) {
            $trashedDocuments = DB::table('documents')
                ->whereNotNull('deleted_at')
                ->select($this->documentSelectColumns())
                ->orderByDesc('documents.deleted_at')
                ->get();
        }

        $duplicateMainPolicies = $this->duplicatePolicies('main_policy_number');
        $duplicateSubPolicies = $this->duplicatePolicies('sub_policy_number');
        $missingMainPolicy = $this->missingPolicies('main_policy_number');
        $missingSubPolicy = $this->missingPolicies('sub_policy_number');

        $summary = [
            'active_documents' => $activeDocumentsCount,
            'without_attachments' => $withoutAttachments->count(),
            'duplicate_main_policies' => $duplicateMainPolicies->count(),
            'duplicate_sub_policies' => $duplicateSubPolicies->count(),
            'missing_main_policy' => $missingMainPolicy->count(),
            'missing_sub_policy' => $missingSubPolicy->count(),
            'trashed_documents' => $trashedDocuments->count(),
            'issues_total' => $withoutAttachments->count()
                + $duplicateMainPolicies->count()
                + $duplicateSubPolicies->count()
                + $missingMainPolicy->count()
                + $missingSubPolicy->count()
                + $trashedDocuments->count(),
        ];

        return view('data-quality.print', compact(
            'generatedAt',
            'generatedBy',
            'summary',
            'withoutAttachments',
            'duplicateMainPolicies',
            'duplicateSubPolicies',
            'missingMainPolicy',
            'missingSubPolicy',
            'trashedDocuments'
        ));
    }

    private function documentSelectColumns(): array
    {
        $columns = ['documents.id'];

        foreach ([
            'reference_number',
            'reference_date',
            'subject',
            'title',
            'main_policy_number',
            'sub_policy_number',
            'deleted_at',
        ] as $column) {
            if (Schema::hasColumn('documents', $column)) {
                $columns[] = 'documents.' . $column;
            }
        }

        return $columns;
    }

    private function duplicatePolicies(string $column)
    {
        if (! Schema::hasColumn('documents', $column)) {
            return collect();
        }

        $base = DB::table('documents')
            ->whereNotNull($column)
            ->whereRaw("TRIM($column) <> ''");

        if (Schema::hasColumn('documents', 'deleted_at')) {
            $base->whereNull('deleted_at');
        }

        return $base
            ->select(
                $column . ' as policy_number',
                DB::raw('COUNT(*) as repeat_count'),
                DB::raw("GROUP_CONCAT(reference_number ORDER BY reference_number SEPARATOR ', ') as linked_references"),
                DB::raw("GROUP_CONCAT(id ORDER BY reference_number SEPARATOR ',') as linked_ids")
            )
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('repeat_count')
            ->orderBy($column)
            ->get();
    }

    private function missingPolicies(string $column)
    {
        if (! Schema::hasColumn('documents', $column)) {
            return collect();
        }

        $query = DB::table('documents')
            ->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhereRaw("TRIM($column) = ''");
            });

        if (Schema::hasColumn('documents', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query
            ->select($this->documentSelectColumns())
            ->orderByDesc('documents.reference_date')
            ->orderByDesc('documents.id')
            ->get();
    }
}
