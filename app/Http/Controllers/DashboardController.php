<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        $stats = [
            'documents_total' => Document::query()->count(),
            'documents_today' => Document::query()->whereDate('reference_date', $today)->count(),
            'documents_month' => Document::query()->whereDate('reference_date', '>=', $startOfMonth)->count(),
            'documents_without_attachment' => Document::query()->doesntHave('attachments')->count(),
            'attachments_total' => DocumentAttachment::query()->count(),
            'departments_total' => Department::query()->count(),
            'document_types_total' => DocumentType::query()->count(),
            'trash_total' => Document::onlyTrashed()->count(),
        ];

        $latestDocuments = Document::query()
            ->with(['department', 'documentType', 'mainAttachment'])
            ->latest()
            ->limit(8)
            ->get();

        $documentsByType = DocumentType::query()
            ->withCount('documents')
            ->orderByDesc('documents_count')
            ->limit(6)
            ->get();

        return view('dashboard.index', compact('stats', 'latestDocuments', 'documentsByType'));
    }
}
