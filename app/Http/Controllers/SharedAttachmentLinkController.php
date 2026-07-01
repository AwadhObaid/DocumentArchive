<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\SharedAttachmentLink;
use App\Models\SharedAttachmentLinkItem;
use App\Services\ActivityLogger;
use App\Services\SecureAttachmentLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SharedAttachmentLinkController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->input('status', 'all');
        if (!in_array($status, ['all', 'active', 'expired', 'disabled'], true)) {
            $status = 'all';
        }

        $query = SharedAttachmentLink::query()
            ->with(['document.department', 'creator'])
            ->withCount('items')
            ->latest();

        if ($status === 'active') {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
        } elseif ($status === 'expired') {
            $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
        } elseif ($status === 'disabled') {
            $query->where('is_active', false);
        }

        $links = $query->paginate(15)->withQueryString();

        $summary = [
            'total' => SharedAttachmentLink::query()->count(),
            'active' => SharedAttachmentLink::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
            'expired' => SharedAttachmentLink::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count(),
            'downloads' => (int) SharedAttachmentLink::query()->sum('download_count'),
        ];

        return view('shared-attachment-links.index', compact('links', 'summary', 'status'));
    }

    public function create(Request $request): View
    {
        $document = null;

        if ($request->filled('document_id')) {
            $document = Document::query()
                ->with(['department', 'documentType', 'attachments'])
                ->findOrFail((int) $request->document_id);
        }

        $documents = Document::query()
            ->with(['department', 'documentType'])
            ->withCount('attachments')
            ->latest('id')
            ->limit(120)
            ->get();

        return view('shared-attachment-links.create', compact('document', 'documents'));
    }

    public function store(Request $request, SecureAttachmentLinkService $service): RedirectResponse
    {
        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'attachment_ids' => ['required', 'array', 'min:1'],
            'attachment_ids.*' => ['integer', 'exists:document_attachments,id'],
            'expires_in' => ['required', 'in:1h,3h,12h,24h,3d,7d'],
            'max_downloads' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'password' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $document = Document::query()
            ->with(['department', 'documentType', 'attachments'])
            ->findOrFail((int) $validated['document_id']);

        $invalidAttachment = collect($validated['attachment_ids'])
            ->map(fn ($id) => (int) $id)
            ->first(fn ($id) => !$document->attachments->contains('id', $id));

        if ($invalidAttachment) {
            return back()
                ->withErrors(['attachment_ids' => 'تم اختيار مرفق لا يتبع هذا الكتاب.'])
                ->withInput();
        }

        try {
            $link = $service->createForDocument(
                $document,
                (array) $validated['attachment_ids'],
                Auth::id(),
                (string) $validated['expires_in'],
                isset($validated['max_downloads']) ? (int) $validated['max_downloads'] : null,
                $validated['password'] ?? null,
                $validated['notes'] ?? null
            );
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['attachment_ids' => $exception->getMessage()])
                ->withInput();
        }

        ActivityLogger::log(
            'shared_attachment_link.created',
            'تم إنشاء رابط مشاركة آمن لمرفقات الكتاب رقم ' . ($document->reference_number ?: $document->id),
            $link,
            [
                'document_id' => $document->id,
                'reference_number' => $document->reference_number,
                'attachments_count' => $link->items()->count(),
                'expires_at' => $link->expires_at?->toDateTimeString(),
            ]
        );

        return redirect()
            ->route('shared-attachment-links.show', $link)
            ->with('success', 'تم إنشاء رابط المشاركة الآمن بنجاح.');
    }

    public function show(SharedAttachmentLink $sharedAttachmentLink): View
    {
        $sharedAttachmentLink->load(['document.department', 'document.documentType', 'creator', 'items.attachment']);

        return view('shared-attachment-links.show', ['link' => $sharedAttachmentLink]);
    }

    public function revoke(SharedAttachmentLink $sharedAttachmentLink): RedirectResponse
    {
        $sharedAttachmentLink->update(['is_active' => false]);

        ActivityLogger::log(
            'shared_attachment_link.revoked',
            'تم تعطيل رابط مشاركة آمن للكتاب رقم ' . ($sharedAttachmentLink->document?->reference_number ?: $sharedAttachmentLink->document_id),
            $sharedAttachmentLink,
            ['document_id' => $sharedAttachmentLink->document_id]
        );

        return back()->with('success', 'تم تعطيل رابط المشاركة.');
    }

    public function destroy(SharedAttachmentLink $sharedAttachmentLink): RedirectResponse
    {
        $sharedAttachmentLink->delete();

        return redirect()
            ->route('shared-attachment-links.index')
            ->with('success', 'تم حذف رابط المشاركة من السجل.');
    }

    public function publicShow(Request $request, string $token): View
    {
        $link = $this->publicLink($token);

        if (!$link || !$link->is_available) {
            return view('shared-attachment-links.public.unavailable', compact('link'));
        }

        if ($link->requires_password && !$this->isUnlocked($request, $link)) {
            return view('shared-attachment-links.public.password', compact('link'));
        }

        $link->increment('view_count');
        $link->forceFill(['last_viewed_at' => now()])->save();

        return view('shared-attachment-links.public.show', compact('link'));
    }

    public function publicUnlock(Request $request, string $token): RedirectResponse
    {
        $link = $this->publicLink($token);

        if (!$link || !$link->is_available) {
            return redirect()->route('shared-attachments.public.show', $token);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'max:100'],
        ]);

        if (!$link->password_hash || !Hash::check((string) $validated['password'], $link->password_hash)) {
            return back()->withErrors(['password' => 'كلمة المرور غير صحيحة.']);
        }

        $request->session()->put($this->unlockSessionKey($link), true);

        return redirect()->route('shared-attachments.public.show', $token);
    }

    public function publicDownload(Request $request, string $token, SharedAttachmentLinkItem $item)
    {
        $link = $this->publicLink($token);

        if (!$link || !$link->is_available || (int) $item->shared_attachment_link_id !== (int) $link->id) {
            abort(404);
        }

        if ($link->requires_password && !$this->isUnlocked($request, $link)) {
            return redirect()->route('shared-attachments.public.show', $token);
        }

        $attachment = $item->attachment;
        abort_unless($attachment, 404);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (!$disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $link->increment('download_count');
        $link->forceFill(['last_downloaded_at' => now()])->save();

        $item->increment('download_count');
        $item->forceFill(['last_downloaded_at' => now()])->save();

        $fileName = $attachment->original_name ?: $attachment->file_name;

        if (method_exists($disk, 'path')) {
            return response()->download($disk->path($attachment->file_path), $fileName);
        }

        return response()->streamDownload(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->file_path);

            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $fileName);
    }

    private function publicLink(string $token): ?SharedAttachmentLink
    {
        return SharedAttachmentLink::query()
            ->with(['document.department', 'document.documentType', 'items.attachment'])
            ->where('token', $token)
            ->first();
    }

    private function isUnlocked(Request $request, SharedAttachmentLink $link): bool
    {
        return (bool) $request->session()->get($this->unlockSessionKey($link));
    }

    private function unlockSessionKey(SharedAttachmentLink $link): string
    {
        return 'shared_attachment_link_unlocked_' . $link->id;
    }
}
