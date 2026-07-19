<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Services\ActivityLogger;
use App\Services\BookAttachmentSmartPathService;
use App\Services\ScannerInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScannerInboxController extends Controller
{
    public function index(Request $request, ScannerInboxService $scanner)
    {
        $this->authorizeScanner();

        $inboxPath = $scanner->path();
        $scannerError = null;

        try {
            $files = $scanner->files(300);
        } catch (\Throwable $exception) {
            $files = [];
            $scannerError = $exception->getMessage();
        }

        $q = trim((string) $request->query('q', ''));

        $documents = Document::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('reference_number', 'like', '%' . $q . '%')
                        ->orWhere('title', 'like', '%' . $q . '%')
                        ->orWhere('subject', 'like', '%' . $q . '%')
                        ->orWhere('main_policy_number', 'like', '%' . $q . '%')
                        ->orWhere('sub_policy_number', 'like', '%' . $q . '%');
                });
            })
            ->latest('id')
            ->limit(50)
            ->get();

        return view('scanner_inbox.index', compact('inboxPath', 'files', 'documents', 'q', 'scannerError'));
    }

    public function updatePath(Request $request, ScannerInboxService $scanner)
    {
        $this->authorizeScannerSettings();

        $validated = $request->validate([
            'scanner_inbox_path' => ['required', 'string', 'max:1000'],
        ], [
            'scanner_inbox_path.required' => 'مسار صندوق الماسح مطلوب.',
        ]);

        try {
            $path = $scanner->savePath($validated['scanner_inbox_path']);
        } catch (\Throwable $exception) {
            return back()->withErrors(['scanner_inbox_path' => $exception->getMessage()])->withInput();
        }

        ActivityLogger::log('scanner.inbox_path.updated', 'تم تعديل مسار صندوق الماسح الضوئي.', null, ['path' => $path]);

        return back()->with('success', 'تم حفظ مسار صندوق الماسح بنجاح.');
    }

    public function attach(Request $request, ScannerInboxService $scanner, BookAttachmentSmartPathService $pathService)
    {
        $this->authorizeScanner();

        $validated = $request->validate([
            'file' => ['required', 'string', 'max:500'],
            'document_id' => ['required', 'exists:documents,id'],
            'make_main' => ['nullable', 'boolean'],
            'keep_source' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'اختر ملفاً من صندوق الماسح.',
            'document_id.required' => 'اختر الكتاب المراد ربط الملف به.',
        ]);

        $fileName = $scanner->safeBaseName($validated['file']);
        $absolutePath = $scanner->absoluteFilePath($fileName);

        if (! is_file($absolutePath)) {
            return back()->withErrors(['file' => 'الملف لم يعد موجوداً في صندوق الماسح.'])->withInput();
        }

        $document = Document::query()->findOrFail((int) $validated['document_id']);

        if (method_exists($document, 'canBeModifiedBy') && ! $document->canBeModifiedBy(Auth::user())) {
            abort(403, 'هذا الكتاب مؤرشف نهائياً ولا يمكن ربط مرفقات جديدة به إلا بصلاحية عليا.');
        }

        $attachment = DB::transaction(function () use ($document, $absolutePath, $fileName, $pathService, $request) {
            $latestVersion = DocumentAttachment::query()
                ->where('document_id', $document->id)
                ->max('version_no');

            $versionNo = ((int) $latestVersion) + 1;
            $stored = $pathService->storeExistingFile($document, $absolutePath, $fileName, $versionNo);
            $classification = $stored['classification'];
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: pathinfo($stored['file_name'], PATHINFO_EXTENSION) ?: 'bin');
            $makeMain = $request->boolean('make_main', true);

            if ($makeMain) {
                DocumentAttachment::query()
                    ->where('document_id', $document->id)
                    ->where('is_main', true)
                    ->update(['is_main' => false]);
            }

            return DocumentAttachment::create([
                'document_id' => $document->id,
                'attachment_type' => 'scanner',
                'version_no' => $versionNo,
                'is_main' => $makeMain,
                'original_name' => $fileName,
                'file_name' => $stored['file_name'],
                'file_path' => $stored['file_path'],
                'disk' => $stored['disk'],
                'storage_root_path' => $stored['storage_root_path'],
                'classification_company_name' => $classification['company'],
                'classification_operation_name' => $classification['operation'],
                'classification_year' => $classification['year'],
                'classification_folder' => $classification['folder'],
                'extension' => $extension,
                'mime_type' => $this->mimeType($absolutePath, $extension),
                'file_size' => is_file($absolutePath) ? @filesize($absolutePath) : null,
                'ocr_status' => 'pending',
                'uploaded_by' => Auth::id(),
            ]);
        });

        $keepSource = $request->boolean('keep_source', true);
        if (! $keepSource && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        ActivityLogger::log(
            'scanner.file.attached',
            'تم ربط ملف ممسوح ضوئياً بالكتاب رقم ' . $document->reference_number,
            $attachment,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'scanner_file' => $fileName,
                'keep_source' => $keepSource,
            ]
        );

        return redirect()
            ->route('scanner-inbox.index', ['q' => $document->reference_number])
            ->with('success', 'تم ربط ملف الماسح بالكتاب بنجاح.');
    }

    private function mimeType(string $path, string $extension): string
    {
        $detected = function_exists('mime_content_type') ? @mime_content_type($path) : null;
        if (is_string($detected) && $detected !== '') {
            return $detected;
        }

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function authorizeScanner(): void
    {
        $user = auth()->user();
        $allowed = $user && method_exists($user, 'hasPermission')
            && ($user->hasPermission('scanner.workflow') || $user->hasPermission('documents.edit') || $user->hasPermission('settings.manage'));

        abort_unless($allowed, 403, 'غير مصرح لك باستخدام صندوق الماسح.');
    }

    private function authorizeScannerSettings(): void
    {
        $user = auth()->user();
        $allowed = $user && method_exists($user, 'hasPermission')
            && ($user->hasPermission('scanner.settings') || $user->hasPermission('settings.manage'));

        abort_unless($allowed, 403, 'غير مصرح لك بتعديل مسار صندوق الماسح.');
    }
}
