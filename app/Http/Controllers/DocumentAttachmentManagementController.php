<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Services\ActivityLogger;
use App\Services\BookAttachmentSmartPathService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentAttachmentManagementController extends Controller
{
    public function history(Document $document)
    {
        $this->authorizeHistory($document);

        $attachments = DocumentAttachment::withTrashed()
            ->where('document_id', $document->id)
            ->with([
                'uploader',
                'deletedBy',
                'replacesAttachment',
                'replacedByAttachment',
            ])
            ->orderByDesc('version_no')
            ->orderByDesc('id')
            ->get();

        $user = Auth::user();
        $canManage = $this->canManage($document);
        $canPreview = $user
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('attachments.preview');
        $canPermanentlyDelete = $this->canPermanentlyDelete($document);
        $pathService = app(BookAttachmentSmartPathService::class);

        return view(
            'documents.attachment-history',
            compact(
                'document',
                'attachments',
                'canManage',
                'canPreview',
                'canPermanentlyDelete',
                'pathService'
            )
        );
    }

    public function replace(Request $request, DocumentAttachment $attachment)
    {
        $attachment->load('document');
        $document = $attachment->document;

        abort_unless($document, 404, 'الكتاب المرتبط بالمرفق غير موجود.');
        $this->authorizeManage($document);

        $validated = $request->validate([
            'replacement_file' => ['required', 'file', 'max:20480'],
            'replacement_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('replacement_file');

        if (! $file || ! $file->isValid()) {
            return back()->withErrors([
                'replacement_file' => 'الملف البديل غير صالح أو لم يكتمل رفعه.',
            ]);
        }

        $metadata = $this->captureUploadMetadata($file);

        $latestVersion = DocumentAttachment::withTrashed()
            ->where('document_id', $document->id)
            ->max('version_no');

        $versionNo = ((int) $latestVersion) + 1;
        $pathService = app(BookAttachmentSmartPathService::class);
        $storedFile = $pathService->storeUploadedFile(
            $document,
            $file,
            $versionNo
        );

        try {
            $newAttachment = DB::transaction(function () use (
                $attachment,
                $document,
                $validated,
                $metadata,
                $versionNo,
                $storedFile
            ) {
                $wasMain = (bool) $attachment->is_main;

                if ($wasMain) {
                    DocumentAttachment::query()
                        ->where('document_id', $document->id)
                        ->update(['is_main' => false]);
                }

                $classification = $storedFile['classification'];

                $newAttachment = DocumentAttachment::create([
                    'document_id' => $document->id,
                    'attachment_type' => $attachment->attachment_type ?: 'main',
                    'version_no' => $versionNo,
                    'is_main' => $wasMain,
                    'original_name' => $metadata['original_name'],
                    'file_name' => $storedFile['file_name'],
                    'file_path' => $storedFile['file_path'],
                    'disk' => $storedFile['disk'],
                    'storage_root_path' => $storedFile['storage_root_path'],
                    'classification_company_name' => $classification['company'],
                    'classification_operation_name' => $classification['operation'],
                    'classification_year' => $classification['year'],
                    'classification_folder' => $classification['folder'],
                    'extension' => $metadata['extension'],
                    'mime_type' => $metadata['mime_type'],
                    'file_size' => $metadata['file_size'],
                    'ocr_status' => 'pending',
                    'uploaded_by' => Auth::id(),
                    'replaces_attachment_id' => $attachment->id,
                ]);

                $attachment->forceFill([
                    'is_main' => false,
                    'deleted_by' => Auth::id(),
                    'replacement_reason' => $validated['replacement_reason'],
                    'replaced_by_attachment_id' => $newAttachment->id,
                    'replaced_at' => now(),
                ])->save();

                $attachment->delete();

                return $newAttachment;
            });
        } catch (\Throwable $exception) {
            $absolutePath = $storedFile['absolute_path'] ?? null;

            if (is_string($absolutePath)
                && $absolutePath !== ''
                && is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            throw $exception;
        }

        ActivityLogger::log(
            'attachment.replaced',
            'تم استبدال مرفق للكتاب رقم ' . $document->reference_number,
            $newAttachment,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'old_attachment_id' => $attachment->id,
                'new_attachment_id' => $newAttachment->id,
                'old_file_name' => $attachment->original_name
                    ?: $attachment->file_name,
                'new_file_name' => $newAttachment->original_name
                    ?: $newAttachment->file_name,
                'reason' => $validated['replacement_reason'],
            ]
        );

        return redirect()
            ->route('documents.show', $document)
            ->with(
                'success',
                'تم استبدال المرفق بنجاح، مع الاحتفاظ بالإصدار السابق في السجل.'
            );
    }

    public function destroy(Request $request, DocumentAttachment $attachment)
    {
        $attachment->load('document');
        $document = $attachment->document;

        abort_unless($document, 404, 'الكتاب المرتبط بالمرفق غير موجود.');
        $this->authorizeManage($document);

        $validated = $request->validate([
            'deletion_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($attachment, $document, $validated) {
            $wasMain = (bool) $attachment->is_main;

            $attachment->forceFill([
                'is_main' => false,
                'deleted_by' => Auth::id(),
                'deletion_reason' => $validated['deletion_reason'],
            ])->save();

            $attachment->delete();

            if ($wasMain) {
                $nextAttachment = DocumentAttachment::query()
                    ->where('document_id', $document->id)
                    ->latest('version_no')
                    ->latest('id')
                    ->first();

                if ($nextAttachment) {
                    $nextAttachment->forceFill(['is_main' => true])->save();
                }
            }
        });

        ActivityLogger::log(
            'attachment.deleted',
            'تم حذف مرفق ظاهريًا من الكتاب رقم '
                . $document->reference_number,
            $attachment,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->original_name
                    ?: $attachment->file_name,
                'reason' => $validated['deletion_reason'],
                'physical_file_deleted' => false,
            ]
        );

        return redirect()
            ->route('documents.show', $document)
            ->with(
                'success',
                'تم حذف المرفق ظاهريًا. يمكن استعادته من سجل المرفقات.'
            );
    }

    public function restore(Document $document, int $attachment)
    {
        $this->authorizeManage($document);

        $record = DocumentAttachment::withTrashed()
            ->where('document_id', $document->id)
            ->findOrFail($attachment);

        if (! $record->trashed()) {
            return back()->with('success', 'المرفق موجود بالفعل.');
        }

        if ($record->replaced_by_attachment_id) {
            return back()->withErrors([
                'attachment' => 'هذا إصدار مستبدل. يمكن تنزيله من السجل، ولا يُستعاد فوق الإصدار الحالي مباشرة.',
            ]);
        }

        $pathService = app(BookAttachmentSmartPathService::class);

        if (! $pathService->attachmentExists($record)) {
            return back()->withErrors([
                'attachment' => 'تعذر استعادة المرفق لأن ملفه غير موجود على وسيط التخزين.',
            ]);
        }

        DB::transaction(function () use ($record, $document) {
            $hasMain = DocumentAttachment::query()
                ->where('document_id', $document->id)
                ->where('is_main', true)
                ->exists();

            $record->restore();

            $record->forceFill([
                'is_main' => ! $hasMain,
                'deleted_by' => null,
                'deletion_reason' => null,
            ])->save();
        });

        ActivityLogger::log(
            'attachment.restored',
            'تمت استعادة مرفق للكتاب رقم ' . $document->reference_number,
            $record,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'attachment_id' => $record->id,
                'file_name' => $record->original_name ?: $record->file_name,
            ]
        );

        return back()->with('success', 'تمت استعادة المرفق بنجاح.');
    }

    public function archivedPreview(Document $document, int $attachment)
    {
        $this->authorizeArchivedPreview($document);

        $record = DocumentAttachment::withTrashed()
            ->where('document_id', $document->id)
            ->findOrFail($attachment);

        $pathService = app(BookAttachmentSmartPathService::class);

        if (! $pathService->attachmentExists($record)) {
            abort(404, 'ملف هذا الإصدار غير موجود على وسيط التخزين.');
        }

        ActivityLogger::log(
            'attachment.version_previewed',
            'تمت معاينة إصدار محفوظ لمرفق الكتاب رقم '
                . $document->reference_number,
            $record,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'attachment_id' => $record->id,
                'file_name' => $record->original_name ?: $record->file_name,
            ]
        );

        $extension = strtolower(
            $record->extension
                ?: pathinfo(
                    $record->original_name ?: $record->file_name,
                    PATHINFO_EXTENSION
                )
        );

        $mimeType = $this->mimeTypeForAttachment($record);
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="attachment-preview.'
                . ($extension ?: 'bin')
                . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $absolutePath = $pathService->absolutePathForAttachment($record);

        if ($absolutePath && is_file($absolutePath)) {
            return response()->file($absolutePath, $headers);
        }

        $binary = $pathService->attachmentBinary($record);

        if ($binary === null) {
            abort(404, 'تعذر قراءة ملف هذا الإصدار.');
        }

        return response($binary, 200, $headers);
    }

    public function forceDelete(
        Request $request,
        Document $document,
        int $attachment
    ) {
        $this->authorizePermanentlyDelete($document);

        $validated = $request->validate([
            'permanent_deletion_reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
            'confirmation_text' => [
                'required',
                'string',
                'in:حذف نهائي',
            ],
        ], [
            'confirmation_text.in' => 'اكتب عبارة «حذف نهائي» كما هي لتأكيد العملية.',
        ]);

        $record = DocumentAttachment::withTrashed()
            ->where('document_id', $document->id)
            ->findOrFail($attachment);

        $pathService = app(BookAttachmentSmartPathService::class);
        $absolutePath = $pathService->absolutePathForAttachment($record);
        $fileExistedBeforeDeletion = $pathService->attachmentExists($record);
        $fileName = $record->original_name ?: $record->file_name;
        $recordId = $record->id;
        $wasMain = (bool) $record->is_main;

        $sameFileUsedElsewhere = DocumentAttachment::withTrashed()
            ->whereKeyNot($record->id)
            ->where('file_path', $record->file_path)
            ->where(function ($query) use ($record) {
                if ($record->disk === null) {
                    $query->whereNull('disk');
                } else {
                    $query->where('disk', $record->disk);
                }
            })
            ->where(function ($query) use ($record) {
                if ($record->storage_root_path === null) {
                    $query->whereNull('storage_root_path');
                } else {
                    $query->where(
                        'storage_root_path',
                        $record->storage_root_path
                    );
                }
            })
            ->exists();

        DB::transaction(function () use ($record, $document, $wasMain) {
            $record->textIndex()->delete();
            $record->forceDelete();

            if ($wasMain) {
                $nextAttachment = DocumentAttachment::query()
                    ->where('document_id', $document->id)
                    ->latest('version_no')
                    ->latest('id')
                    ->first();

                if ($nextAttachment) {
                    DocumentAttachment::query()
                        ->where('document_id', $document->id)
                        ->update(['is_main' => false]);

                    $nextAttachment->forceFill(['is_main' => true])->save();
                }
            }
        });

        $physicalFileDeleted = false;
        $physicalDeleteFailed = false;

        if (! $sameFileUsedElsewhere
            && is_string($absolutePath)
            && $absolutePath !== ''
            && is_file($absolutePath)) {
            $physicalFileDeleted = @unlink($absolutePath);
            $physicalDeleteFailed = ! $physicalFileDeleted
                && is_file($absolutePath);
        }

        ActivityLogger::log(
            'attachment.force_deleted',
            'تم حذف مرفق نهائيًا من الكتاب رقم '
                . $document->reference_number,
            $record,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'attachment_id' => $recordId,
                'file_name' => $fileName,
                'reason' => $validated['permanent_deletion_reason'],
                'file_existed_before_deletion' => $fileExistedBeforeDeletion,
                'physical_file_deleted' => $physicalFileDeleted,
                'physical_file_shared_with_another_record'
                    => $sameFileUsedElsewhere,
                'physical_file_delete_failed' => $physicalDeleteFailed,
            ]
        );

        if ($physicalDeleteFailed) {
            return back()->with(
                'warning',
                'تم حذف سجل المرفق نهائيًا، لكن تعذر حذف الملف من وسيط '
                . 'التخزين. راجع صلاحيات المجلد.'
            );
        }

        if ($sameFileUsedElsewhere) {
            return back()->with(
                'success',
                'تم حذف سجل المرفق نهائيًا. لم يُحذف الملف الفعلي لأنه '
                . 'مرتبط بسجل مرفق آخر.'
            );
        }

        return back()->with(
            'success',
            'تم حذف المرفق نهائيًا من السجل ووسيط التخزين.'
        );
    }

    public function archivedDownload(Document $document, int $attachment)
    {
        $this->authorizeManage($document);

        $record = DocumentAttachment::withTrashed()
            ->where('document_id', $document->id)
            ->findOrFail($attachment);

        $pathService = app(BookAttachmentSmartPathService::class);

        if (! $pathService->attachmentExists($record)) {
            abort(404, 'ملف هذا الإصدار غير موجود على وسيط التخزين.');
        }

        ActivityLogger::log(
            'attachment.version_downloaded',
            'تم تنزيل إصدار محفوظ لمرفق الكتاب رقم '
                . $document->reference_number,
            $record,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'attachment_id' => $record->id,
                'file_name' => $record->original_name ?: $record->file_name,
            ]
        );

        $fileName = $record->original_name ?: $record->file_name;
        $absolutePath = $pathService->absolutePathForAttachment($record);

        if ($absolutePath && is_file($absolutePath)) {
            return response()->download($absolutePath, $fileName);
        }

        $binary = $pathService->attachmentBinary($record);

        if ($binary === null) {
            abort(404, 'تعذر قراءة ملف هذا الإصدار.');
        }

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $fileName);
    }

    private function captureUploadMetadata(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: pathinfo($originalName, PATHINFO_EXTENSION)
            ?: 'bin'
        );

        $fileSize = $file->getSize();

        if ($fileSize === false || $fileSize === null) {
            $temporaryPath = $file->getPathname();
            $fileSize = is_file($temporaryPath)
                ? (int) filesize($temporaryPath)
                : 0;
        }

        try {
            $mimeType = $file->getMimeType();
        } catch (\Throwable $exception) {
            $mimeType = null;
        }

        return [
            'original_name' => $originalName,
            'extension' => $extension,
            'file_size' => (int) $fileSize,
            'mime_type' => $mimeType
                ?: $file->getClientMimeType()
                ?: 'application/octet-stream',
        ];
    }

    private function mimeTypeForAttachment(
        DocumentAttachment $attachment
    ): string {
        $extension = strtolower(
            $attachment->extension
                ?: pathinfo(
                    $attachment->original_name ?: $attachment->file_name,
                    PATHINFO_EXTENSION
                )
        );

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain; charset=UTF-8',
            default => $attachment->mime_type
                ?: 'application/octet-stream',
        };
    }

    private function authorizeArchivedPreview(Document $document): void
    {
        $this->authorizeHistory($document);

        $user = Auth::user();
        $allowed = $user
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('attachments.preview');

        abort_unless(
            $allowed,
            403,
            'غير مصرح لك بمعاينة هذا المرفق.'
        );
    }

    private function authorizePermanentlyDelete(Document $document): void
    {
        abort_unless(
            $this->canPermanentlyDelete($document),
            403,
            'غير مصرح لك بالحذف النهائي للمرفقات.'
        );
    }

    private function canPermanentlyDelete(Document $document): bool
    {
        $user = Auth::user();

        if (! $user
            || ! method_exists($user, 'hasPermission')
            || ! $user->hasPermission('documents.force_delete')) {
            return false;
        }

        return ! method_exists($document, 'canBeModifiedBy')
            || $document->canBeModifiedBy($user);
    }

    private function authorizeHistory(Document $document): void
    {
        $user = Auth::user();

        $allowed = $user
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('documents.view');

        abort_unless(
            $allowed,
            403,
            'غير مصرح لك بعرض سجل مرفقات هذا الكتاب.'
        );
    }

    private function authorizeManage(Document $document): void
    {
        abort_unless(
            $this->canManage($document),
            403,
            'غير مصرح لك بحذف أو استبدال مرفقات هذا الكتاب.'
        );
    }

    private function canManage(Document $document): bool
    {
        $user = Auth::user();

        if (! $user
            || ! method_exists($user, 'hasPermission')
            || ! $user->hasPermission('documents.edit')) {
            return false;
        }

        return ! method_exists($document, 'canBeModifiedBy')
            || $document->canBeModifiedBy($user);
    }
}
