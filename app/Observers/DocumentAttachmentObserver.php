<?php

namespace App\Observers;

use App\Models\DocumentAttachment;
use App\Services\SystemNotificationService;
use Illuminate\Support\Facades\Auth;

class DocumentAttachmentObserver
{
    public function created(DocumentAttachment $attachment): void
    {
        $document = $this->document($attachment);
        SystemNotificationService::notifyAdmins(
            'تم رفع مرفق جديد',
            'تم رفع مرفق جديد للكتاب رقم ' . $this->ref($document, $attachment) . ' بواسطة ' . $this->actorName() . '.',
            'attachment_uploaded',
            $this->documentUrl($document, $attachment),
            ['attachment_id' => $attachment->id, 'document_id' => $attachment->document_id]
        );
    }

    public function deleted(DocumentAttachment $attachment): void
    {
        $document = $this->document($attachment);
        SystemNotificationService::notifyAdmins(
            'تم حذف مرفق',
            'تم حذف مرفق من الكتاب رقم ' . $this->ref($document, $attachment) . ' بواسطة ' . $this->actorName() . '.',
            'attachment_deleted',
            $this->documentUrl($document, $attachment),
            ['attachment_id' => $attachment->id, 'document_id' => $attachment->document_id]
        );
    }

    private function document(DocumentAttachment $attachment): mixed
    {
        try {
            if (method_exists($attachment, 'document')) {
                return $attachment->document()->withTrashed()->first() ?? $attachment->document;
            }
        } catch (\Throwable $e) {
            // Ignore and use fallback below.
        }
        return $attachment->document ?? null;
    }

    private function ref(mixed $document, DocumentAttachment $attachment): string
    {
        return (string) ($document->reference_number ?? $document->document_no ?? $attachment->document_id ?? 'غير محدد');
    }

    private function actorName(): string
    {
        return (string) (Auth::user()->name ?? Auth::user()->username ?? 'النظام');
    }

    private function documentUrl(mixed $document, DocumentAttachment $attachment): string
    {
        try {
            if ($document && isset($document->id)) {
                return route('documents.show', $document);
            }
        } catch (\Throwable $e) {
            // Fallback below.
        }
        return url('/documents/' . ($attachment->document_id ?? ''));
    }
}