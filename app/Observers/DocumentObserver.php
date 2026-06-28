<?php

namespace App\Observers;

use App\Models\Document;
use App\Services\SystemNotificationService;
use Illuminate\Support\Facades\Auth;

class DocumentObserver
{
    public function created(Document $document): void
    {
        SystemNotificationService::notifyAdmins(
            'تم إنشاء كتاب جديد',
            'تم إنشاء الكتاب رقم ' . $this->ref($document) . ' بواسطة ' . $this->actorName() . '.',
            'document_created',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document)]
        );
    }

    public function updated(Document $document): void
    {
        $changed = array_diff(array_keys($document->getChanges()), ['updated_at']);
        if (empty($changed)) {
            return;
        }

        SystemNotificationService::notifyAdmins(
            'تم تعديل كتاب',
            'تم تعديل بيانات الكتاب رقم ' . $this->ref($document) . ' بواسطة ' . $this->actorName() . '.',
            'document_updated',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document), 'changed' => array_values($changed)]
        );
    }

    public function deleted(Document $document): void
    {
        SystemNotificationService::notifyAdmins(
            'تم حذف كتاب',
            'تم نقل الكتاب رقم ' . $this->ref($document) . ' إلى سلة المحذوفات بواسطة ' . $this->actorName() . '.',
            'document_deleted',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document)]
        );
    }

    public function restored(Document $document): void
    {
        SystemNotificationService::notifyAdmins(
            'تم استعادة كتاب',
            'تم استعادة الكتاب رقم ' . $this->ref($document) . ' بواسطة ' . $this->actorName() . '.',
            'document_restored',
            $this->documentUrl($document),
            ['document_id' => $document->id, 'reference_number' => $this->ref($document)]
        );
    }

    private function ref(Document $document): string
    {
        return (string) ($document->reference_number ?? $document->document_no ?? $document->id ?? 'غير محدد');
    }

    private function actorName(): string
    {
        return (string) (Auth::user()->name ?? Auth::user()->username ?? 'النظام');
    }

    private function documentUrl(Document $document): string
    {
        try {
            return route('documents.show', $document);
        } catch (\Throwable $e) {
            return url('/documents/' . $document->id);
        }
    }
}