<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        $actions = ActivityLog::query()
            ->select('action')
            ->whereNotNull('action')
            ->distinct()
            ->pluck('action')
            ->filter()
            ->sortBy(fn ($action) => ActivityLog::actionLabel($action))
            ->values();

        $modelTypes = ActivityLog::query()
            ->select('model_type')
            ->whereNotNull('model_type')
            ->distinct()
            ->pluck('model_type')
            ->filter()
            ->sortBy(fn ($modelType) => ActivityLog::modelLabel($modelType))
            ->values();

        $filteredQuery = $this->filteredQuery($request)->with('user');

        $summary = [
            'total_filtered' => (clone $filteredQuery)->count(),
            'today_total' => ActivityLog::query()->whereDate('created_at', today())->count(),
            'users_total' => ActivityLog::query()->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'critical_total' => ActivityLog::query()
                ->where(function ($query) {
                    $query
                        ->where('action', 'like', '%.deleted')
                        ->orWhere('action', 'like', '%.force_deleted')
                        ->orWhere('action', 'like', 'backup.%restored')
                        ->orWhere('action', 'settings.updated');
                })
                ->count(),
        ];

        $logs = $filteredQuery
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('activity-logs.index', compact('logs', 'users', 'actions', 'modelTypes', 'summary'));
    }

    public function document(Document $document)
    {
        $attachmentIds = $document->attachments()->pluck('id')->all();

        $logs = ActivityLog::query()
            ->with('user')
            ->where(function ($query) use ($document, $attachmentIds) {
                $query->where(function ($documentQuery) use ($document) {
                    $documentQuery
                        ->where('model_type', Document::class)
                        ->where('model_id', $document->id);
                });

                if (!empty($attachmentIds)) {
                    $query->orWhere(function ($attachmentQuery) use ($attachmentIds) {
                        $attachmentQuery
                            ->where('model_type', DocumentAttachment::class)
                            ->whereIn('model_id', $attachmentIds);
                    });
                }
            })
            ->latest()
            ->paginate(30);

        $summary = [
            'total' => $logs->total(),
            'attachments_total' => count($attachmentIds),
            'last_event' => ActivityLog::query()
                ->where(function ($query) use ($document, $attachmentIds) {
                    $query->where(function ($documentQuery) use ($document) {
                        $documentQuery
                            ->where('model_type', Document::class)
                            ->where('model_id', $document->id);
                    });

                    if (!empty($attachmentIds)) {
                        $query->orWhere(function ($attachmentQuery) use ($attachmentIds) {
                            $attachmentQuery
                                ->where('model_type', DocumentAttachment::class)
                                ->whereIn('model_id', $attachmentIds);
                        });
                    }
                })
                ->latest()
                ->value('created_at'),
        ];

        return view('activity-logs.document', compact('document', 'logs', 'summary'));
    }

    private function filteredQuery(Request $request)
    {
        return ActivityLog::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->q);

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('description', 'like', "%{$q}%")
                        ->orWhere('action', 'like', "%{$q}%")
                        ->orWhere('model_type', 'like', "%{$q}%")
                        ->orWhere('model_id', 'like', "%{$q}%")
                        ->orWhere('ip_address', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when($request->filled('action'), function ($query) use ($request) {
                $query->where('action', $request->action);
            })
            ->when($request->filled('model_type'), function ($query) use ($request) {
                $query->where('model_type', $request->model_type);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->date_to);
            });
    }
}
