<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataQualityController extends Controller
{
    public function index()
    {
        $data = [
            'documentsTableExists' => Schema::hasTable('documents'),
            'attachmentsTableExists' => Schema::hasTable('document_attachments'),
            'withoutAttachments' => collect(),
            'duplicateMainPolicies' => collect(),
            'duplicateSubPolicies' => collect(),
            'withoutMainPolicy' => collect(),
            'withoutSubPolicy' => collect(),
            'trashedDocuments' => collect(),
            'summary' => [
                'without_attachments' => 0,
                'duplicate_main_policies' => 0,
                'duplicate_sub_policies' => 0,
                'trashed_documents' => 0,
                'without_main_policy' => 0,
                'without_sub_policy' => 0,
            ],
        ];

        if (! $data['documentsTableExists']) {
            return view('data-quality.index', $data);
        }

        $base = Document::query();

        $data['withoutAttachments'] = $this->safeWithoutAttachments();
        $data['duplicateMainPolicies'] = $this->duplicatePolicies('main_policy_number');
        $data['duplicateSubPolicies'] = $this->duplicatePolicies('sub_policy_number');
        $data['withoutMainPolicy'] = $this->documentsMissingPolicy('main_policy_number');
        $data['withoutSubPolicy'] = $this->documentsMissingPolicy('sub_policy_number');
        $data['trashedDocuments'] = $this->safeTrashedDocuments();

        $data['summary'] = [
            'without_attachments' => $data['withoutAttachments']->count(),
            'duplicate_main_policies' => $data['duplicateMainPolicies']->count(),
            'duplicate_sub_policies' => $data['duplicateSubPolicies']->count(),
            'trashed_documents' => $data['trashedDocuments']->count(),
            'without_main_policy' => $data['withoutMainPolicy']->count(),
            'without_sub_policy' => $data['withoutSubPolicy']->count(),
        ];

        return view('data-quality.index', $data);
    }

    private function safeWithoutAttachments(): Collection
    {
        if (! Schema::hasTable('document_attachments')) {
            return collect();
        }

        try {
            return Document::query()
                ->whereDoesntHave('attachments')
                ->latest('reference_date')
                ->latest('id')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function duplicatePolicies(string $column): Collection
    {
        if (! Schema::hasColumn('documents', $column)) {
            return collect();
        }

        $duplicates = Document::query()
            ->select($column, DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('duplicate_count')
            ->limit(50)
            ->get();

        return $duplicates->map(function ($row) use ($column) {
            $policy = $row->{$column};

            $documents = Document::query()
                ->where($column, $policy)
                ->orderBy('reference_date')
                ->orderBy('reference_number')
                ->get();

            return [
                'policy' => $policy,
                'count' => (int) $row->duplicate_count,
                'documents' => $documents,
            ];
        });
    }

    private function documentsMissingPolicy(string $column): Collection
    {
        if (! Schema::hasColumn('documents', $column)) {
            return collect();
        }

        return Document::query()
            ->where(function ($query) use ($column) {
                $query->whereNull($column)->orWhere($column, '');
            })
            ->latest('reference_date')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    private function safeTrashedDocuments(): Collection
    {
        try {
            return Document::onlyTrashed()
                ->latest('deleted_at')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}