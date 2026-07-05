<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Memo;
use App\Models\MemoAttachment;
use App\Models\SharedAttachmentLink;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SecureAttachmentLinkService
{
    public function createForDocument(
        Document $document,
        array $attachmentIds,
        ?int $createdBy = null,
        string $expiresIn = '24h',
        ?int $maxDownloads = null,
        ?string $password = null,
        ?string $notes = null
    ): SharedAttachmentLink {
        $attachmentIds = array_values(array_unique(array_filter(array_map('intval', $attachmentIds))));

        if ($attachmentIds === []) {
            $attachmentIds = $document->attachments()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $attachments = DocumentAttachment::query()
            ->where('document_id', $document->id)
            ->whereIn('id', $attachmentIds)
            ->get();

        if ($attachments->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد مرفقات صالحة لإنشاء رابط مشاركة.');
        }

        $link = SharedAttachmentLink::create([
            'document_id' => $document->id,
            'created_by' => $createdBy ?: Auth::id(),
            'token' => $this->uniqueToken(),
            'title' => 'مرفقات الكتاب رقم ' . ($document->reference_number ?: $document->id),
            'notes' => $notes,
            'password_hash' => $password ? Hash::make($password) : null,
            'expires_at' => $this->expiresAt($expiresIn),
            'max_downloads' => $maxDownloads,
            'download_count' => 0,
            'view_count' => 0,
            'is_active' => true,
        ]);

        foreach ($attachments as $attachment) {
            $link->items()->create([
                'document_attachment_id' => $attachment->id,
                'download_count' => 0,
            ]);
        }

        return $link->load(['document.department', 'document.documentType', 'items.attachment']);
    }


    public function createForMemo(
        Memo $memo,
        array $attachmentIds,
        ?int $createdBy = null,
        string $expiresIn = '24h',
        ?int $maxDownloads = null,
        ?string $password = null,
        ?string $notes = null
    ): SharedAttachmentLink {
        $attachmentIds = array_values(array_unique(array_filter(array_map('intval', $attachmentIds))));

        if ($attachmentIds === []) {
            $attachmentIds = $memo->attachments()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $attachments = MemoAttachment::query()
            ->where('memo_id', $memo->id)
            ->whereIn('id', $attachmentIds)
            ->get();

        if ($attachments->isEmpty()) {
            throw new \InvalidArgumentException('لا توجد مرفقات صالحة لإنشاء رابط مشاركة.');
        }

        $link = SharedAttachmentLink::create([
            'document_id' => null,
            'memo_id' => $memo->id,
            'created_by' => $createdBy ?: Auth::id(),
            'token' => $this->uniqueToken(),
            'title' => 'مرفقات المذكرة رقم ' . ($memo->memo_number ?: $memo->id),
            'notes' => $notes,
            'password_hash' => $password ? Hash::make($password) : null,
            'expires_at' => $this->expiresAt($expiresIn),
            'max_downloads' => $maxDownloads,
            'download_count' => 0,
            'view_count' => 0,
            'is_active' => true,
        ]);

        foreach ($attachments as $attachment) {
            $link->items()->create([
                'document_attachment_id' => null,
                'memo_attachment_id' => $attachment->id,
                'download_count' => 0,
            ]);
        }

        return $link->load(['memo.department', 'items.memoAttachment']);
    }

    public function appendLinkToBody(string $body, SharedAttachmentLink $link): string
    {
        $document = $link->document;
        $expires = $link->expires_at ? $link->expires_at->format('Y-m-d H:i') : 'غير محدد';

        $extra = implode("\n", [
            '',
            'رابط المرفقات الآمن:',
            $link->public_url,
            'تنتهي صلاحية الرابط: ' . $expires,
            $link->requires_password ? 'ملاحظة: الرابط محمي بكلمة مرور.' : null,
        ]);

        $extra = implode("\n", array_filter(explode("\n", $extra), fn ($line) => $line !== null));

        return rtrim($body) . "\n" . $extra;
    }

    public function expiresAt(string $expiresIn)
    {
        return match ($expiresIn) {
            '1h' => now()->addHour(),
            '3h' => now()->addHours(3),
            '12h' => now()->addHours(12),
            '3d' => now()->addDays(3),
            '7d' => now()->addDays(7),
            default => now()->addDay(),
        };
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::random(56);
        } while (SharedAttachmentLink::query()->where('token', $token)->exists());

        return $token;
    }
}
