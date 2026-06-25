<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentType;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'documents_count' => Document::query()->count(),
            'today_documents_count' => Document::query()->whereDate('created_at', today())->count(),
            'trashed_documents_count' => Document::onlyTrashed()->count(),
            'attachments_count' => DocumentAttachment::query()->count(),
            'departments_count' => Department::query()->count(),
            'document_types_count' => DocumentType::query()->count(),
            'users_count' => User::query()->count(),
        ];

        $latestDocuments = Document::query()
            ->with(['department', 'documentType'])
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard.index', compact('stats', 'latestDocuments'));
    }
}
