<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataQualityController extends Controller
{
    public function index(): View
    {
        $databaseReady = Schema::hasTable('documents');

        $summary = [
            'without_attachments' => 0,
            'duplicate_main_policies' => 0,
            'duplicate_sub_policies' => 0,
            'missing_main_policy' => 0,
            'missing_sub_policy' => 0,
            'deleted_documents' => 0,
        ];

        $withoutAttachments = collect();
        $duplicateMainPolicies = collect();
        $duplicateSubPolicies = collect();
        $missingMainPolicies = collect();
        $missingSubPolicies = collect();
        $deletedDocuments = collect();

        if ($databaseReady) {
            $hasAttachmentsTable = Schema::hasTable('document_attachments');
            $hasMainPolicy = Schema::hasColumn('documents', 'main_policy_number');
            $hasSubPolicy = Schema::hasColumn('documents', 'sub_policy_number');
            $hasDeletedAt = Schema::hasColumn('documents', 'deleted_at');

            if ($hasAttachmentsTable) {
                $withoutAttachmentsQuery = Document::query()
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('document_attachments')
                            ->whereColumn('document_attachments.document_id', 'documents.id');
                    });

                $summary['without_attachments'] = (clone $withoutAttachmentsQuery)->count();
                $withoutAttachments = (clone $withoutAttachmentsQuery)
                    ->latest('reference_date')
                    ->latest('id')
                    ->limit(20)
                    ->get();
            }

            if ($hasMainPolicy) {
                $duplicateMainPolicies = $this->duplicatePolicyGroups('main_policy_number');
                $summary['duplicate_main_policies'] = $duplicateMainPolicies->count();

                $missingMainQuery = Document::query()
                    ->where(function ($query) {
                        $query->whereNull('main_policy_number')
                            ->orWhere('main_policy_number', '');
                    });

                $summary['missing_main_policy'] = (clone $missingMainQuery)->count();
                $missingMainPolicies = (clone $missingMainQuery)
                    ->latest('reference_date')
                    ->latest('id')
                    ->limit(20)
                    ->get();
            }

            if ($hasSubPolicy) {
                $duplicateSubPolicies = $this->duplicatePolicyGroups('sub_policy_number');
                $summary['duplicate_sub_policies'] = $duplicateSubPolicies->count();

                $missingSubQuery = Document::query()
                    ->where(function ($query) {
                        $query->whereNull('sub_policy_number')
                            ->orWhere('sub_policy_number', '');
                    });

                $summary['missing_sub_policy'] = (clone $missingSubQuery)->count();
                $missingSubPolicies = (clone $missingSubQuery)
                    ->latest('reference_date')
                    ->latest('id')
                    ->limit(20)
                    ->get();
            }

            if ($hasDeletedAt) {
                $deletedQuery = DB::table('documents')
                    ->whereNotNull('deleted_at')
                    ->orderByDesc('deleted_at')
                    ->orderByDesc('id');

                $summary['deleted_documents'] = (clone $deletedQuery)->count();
                $deletedDocuments = collect((clone $deletedQuery)->limit(20)->get());
            }
        }

        return view('data-quality.index', compact(
            'databaseReady',
            'summary',
            'withoutAttachments',
            'duplicateMainPolicies',
            'duplicateSubPolicies',
            'missingMainPolicies',
            'missingSubPolicies',
            'deletedDocuments'
        ));
    }

    private function duplicatePolicyGroups(string $column): Collection
    {
        $groups = DB::table('documents')
            ->select($column, DB::raw('COUNT(*) as total'))
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->when(Schema::hasColumn('documents', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->orderBy($column)
            ->limit(30)
            ->get();

        return $groups->map(function ($group) use ($column) {
            $documents = Document::query()
                ->where($column, $group->{$column})
                ->latest('reference_date')
                ->latest('id')
                ->limit(10)
                ->get();

            return [
                'policy_number' => $group->{$column},
                'total' => (int) $group->total,
                'documents' => $documents,
            ];
        });
    }
}
