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
            ->sortBy(fn ($action) => ActivityLog::actionLabel($action))
            ->values();

        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->q);

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('description', 'like', "%{$q}%")
                        ->orWhere('action', 'like', "%{$q}%")
                        ->orWhere('model_type', 'like', "%{$q}%")
                        ->orWhere('model_id', 'like', "%{$q}%");
                });
            })
            ->when($request->filled('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when($request->filled('action'), function ($query) use ($request) {
                $query->where('action', $request->action);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->date_to);
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('activity-logs.index', compact('logs', 'users', 'actions'));
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
            ->paginate(25);

        return view('activity-logs.document', compact('document', 'logs'));
    }
}
