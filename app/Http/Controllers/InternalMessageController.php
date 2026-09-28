<?php

namespace App\Http\Controllers;

use App\Support\AttachmentUploadLimits;
use App\Models\Document;
use App\Models\InternalMessage;
use App\Models\InternalMessageAttachment;
use App\Models\Memo;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\SystemNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InternalMessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $folder = (string) $request->input('folder', 'inbox');
        if (! in_array($folder, ['inbox', 'sent'], true)) {
            $folder = 'inbox';
        }

        $q = trim((string) $request->input('q', ''));

        $query = InternalMessage::query()
            ->with(['sender', 'receiver', 'document.department', 'memo.department'])
            ->withCount('attachments');

        if ($folder === 'sent') {
            $query->sentFor((int) $user->id);
        } else {
            $query->inboxFor((int) $user->id);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('subject', 'like', '%' . $q . '%')
                    ->orWhere('body', 'like', '%' . $q . '%')
                    ->orWhereHas('sender', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $q . '%')->orWhere('username', 'like', '%' . $q . '%'))
                    ->orWhereHas('receiver', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $q . '%')->orWhere('username', 'like', '%' . $q . '%'))
                    ->orWhereHas('document', fn ($documentQuery) => $documentQuery->where('reference_number', 'like', '%' . $q . '%')->orWhere('title', 'like', '%' . $q . '%')->orWhere('subject', 'like', '%' . $q . '%'))
                    ->orWhereHas('memo', fn ($memoQuery) => $memoQuery->where('memo_number', 'like', '%' . $q . '%')->orWhere('subject', 'like', '%' . $q . '%'));
            });
        }

        $messages = $query->latest('id')->paginate(15)->withQueryString();

        $stats = [
            'inbox' => InternalMessage::query()->inboxFor((int) $user->id)->count(),
            'sent' => InternalMessage::query()->sentFor((int) $user->id)->count(),
            'unread' => InternalMessage::query()->unreadFor((int) $user->id)->count(),
        ];

        return view('internal-messages.index', compact('messages', 'folder', 'q', 'stats'));
    }

    public function create(Request $request)
    {
        $selectedDocument = null;
        $selectedMemo = null;

        if ($request->filled('document_id')) {
            $selectedDocument = Document::query()->with(['department', 'documentType'])->find($request->integer('document_id'));
        }

        if ($request->filled('memo_id')) {
            $selectedMemo = Memo::query()->with('department')->find($request->integer('memo_id'));
        }

        return $this->createView($selectedDocument, $selectedMemo);
    }

    public function createForDocument(Document $document)
    {
        $document->load(['department', 'documentType']);

        return $this->createView($document, null);
    }

    public function createForMemo(Memo $memo)
    {
        $memo->load('department');

        return $this->createView(null, $memo);
    }

    public function store(Request $request)
    {
        $currentUserId = (int) Auth::id();

        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id', 'not_in:' . $currentUserId],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'memo_id' => ['nullable', 'integer', 'exists:memos,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['nullable', 'file', 'max:' . AttachmentUploadLimits::MAX_KB],
        ], [
            'receiver_id.required' => 'يرجى اختيار المستلم.',
            'receiver_id.not_in' => 'لا يمكن إرسال رسالة داخلية لنفس المستخدم.',
            'subject.required' => 'يرجى كتابة عنوان الرسالة.',
            'attachments.*.max' => 'حجم كل مرفق يجب ألا يتجاوز ' . AttachmentUploadLimits::MAX_MB . ' MB.',
        ]);

        $message = DB::transaction(function () use ($request, $validated, $currentUserId) {
            $message = InternalMessage::create([
                'sender_id' => $currentUserId,
                'receiver_id' => (int) $validated['receiver_id'],
                'subject' => $this->normalizeText($validated['subject']),
                'body' => $this->normalizeNullableText($validated['body'] ?? null),
                'document_id' => $validated['document_id'] ?? null,
                'memo_id' => $validated['memo_id'] ?? null,
            ]);

            $this->storeAttachments($request, $message);

            return $message;
        });

        $message->load(['receiver', 'document', 'memo']);
        $this->notifyReceiver($message);

        ActivityLogger::log('internal_message.sent', 'تم إرسال مراسلة داخلية إلى ' . ($message->receiver?->name ?: 'مستخدم'), $message, [
            'internal_message_id' => $message->id,
            'receiver_id' => $message->receiver_id,
            'document_id' => $message->document_id,
            'memo_id' => $message->memo_id,
        ]);

        return redirect()->route('internal-messages.show', $message)->with('success', 'تم إرسال الرسالة الداخلية بنجاح.');
    }

    public function show(InternalMessage $internalMessage)
    {
        $this->authorizeMessageAccess($internalMessage);

        $internalMessage->load(['sender', 'receiver', 'document.department', 'document.documentType', 'memo.department', 'attachments.uploader']);

        if ($internalMessage->isUnreadFor(Auth::user())) {
            $internalMessage->forceFill(['read_at' => now()])->save();
        }

        return view('internal-messages.show', ['message' => $internalMessage]);
    }

    public function previewAttachment(InternalMessage $internalMessage, InternalMessageAttachment $attachment)
    {
        $this->authorizeMessageAccess($internalMessage);
        $this->ensureAttachmentBelongsToMessage($internalMessage, $attachment);

        $internalMessage->load(['sender', 'receiver', 'document', 'memo']);
        $attachment->load('uploader');

        ActivityLogger::log('internal_message_attachment.previewed', 'تمت معاينة مرفق مراسلة داخلية: ' . ($attachment->original_name ?: $attachment->file_name), $attachment, [
            'internal_message_id' => $internalMessage->id,
            'sender_id' => $internalMessage->sender_id,
            'receiver_id' => $internalMessage->receiver_id,
        ]);

        return view('internal-messages.attachment-preview', [
            'message' => $internalMessage,
            'attachment' => $attachment,
        ]);
    }

    public function attachmentData(InternalMessage $internalMessage, InternalMessageAttachment $attachment)
    {
        $this->authorizeMessageAccess($internalMessage);
        $this->ensureAttachmentBelongsToMessage($internalMessage, $attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower($attachment->extension ?: pathinfo($attachment->original_name ?: $attachment->file_name, PATHINFO_EXTENSION));
        $mimeType = $this->mimeTypeFor($extension, $attachment->mime_type ?: null);

        $isPreviewable = $extension === 'pdf' || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) || str_starts_with(strtolower($mimeType), 'image/');
        abort_unless($isPreviewable, 415, 'لا يمكن معاينة هذا النوع داخل المتصفح.');

        $binary = $disk->get($attachment->file_path);

        return response()->json([
            'name' => $attachment->original_name ?: $attachment->file_name,
            'file_name' => $attachment->original_name ?: $attachment->file_name,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => (int) $attachment->file_size,
            'base64' => base64_encode($binary),
        ]);
    }

    public function inlineAttachment(InternalMessage $internalMessage, InternalMessageAttachment $attachment)
    {
        $this->authorizeMessageAccess($internalMessage);
        $this->ensureAttachmentBelongsToMessage($internalMessage, $attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

        $extension = strtolower($attachment->extension ?: pathinfo($attachment->original_name ?: $attachment->file_name, PATHINFO_EXTENSION));
        $mimeType = $this->mimeTypeFor($extension, $attachment->mime_type ?: null);

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="internal-message-attachment.' . ($extension ?: 'bin') . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (method_exists($disk, 'path')) {
            return response()->file($disk->path($attachment->file_path), $headers);
        }

        return response()->stream(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->file_path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function downloadAttachment(InternalMessage $internalMessage, InternalMessageAttachment $attachment)
    {
        $this->authorizeMessageAccess($internalMessage);
        $this->ensureAttachmentBelongsToMessage($internalMessage, $attachment);

        $disk = Storage::disk($attachment->disk ?: 'local');

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'الملف غير موجود.');
        }

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
        }, $fileName, ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']);
    }

    public function archive(InternalMessage $internalMessage)
    {
        $this->authorizeMessageAccess($internalMessage);

        $user = Auth::user();
        if ((int) $internalMessage->receiver_id === (int) $user->id) {
            $internalMessage->forceFill(['receiver_archived_at' => now()])->save();
        }
        if ((int) $internalMessage->sender_id === (int) $user->id) {
            $internalMessage->forceFill(['sender_archived_at' => now()])->save();
        }

        return redirect()->route('internal-messages.index')->with('success', 'تم أرشفة الرسالة من صندوقك.');
    }

    private function createView(?Document $selectedDocument = null, ?Memo $selectedMemo = null)
    {
        $users = User::query()
            ->where('id', '<>', Auth::id())
            ->when(method_exists(User::class, 'query'), function ($query) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
                    $query->where('is_active', true);
                }
            })
            ->orderBy('name')
            ->get();

        $documents = Document::query()
            ->select(['id', 'reference_number', 'title', 'subject'])
            ->latest('id')
            ->limit(100)
            ->get();

        $memos = Memo::query()
            ->select(['id', 'memo_number', 'subject'])
            ->latest('id')
            ->limit(100)
            ->get();

        return view('internal-messages.create', compact('users', 'documents', 'memos', 'selectedDocument', 'selectedMemo'));
    }

    private function storeAttachments(Request $request, InternalMessage $message): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $files = $request->file('attachments');
        if (! is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $safeName = 'msg_' . $message->id . '_' . Str::random(14) . '.' . $extension;
            $folder = 'internal-messages/' . $message->id;
            $path = $file->storeAs($folder, $safeName, 'local');

            InternalMessageAttachment::create([
                'internal_message_id' => $message->id,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => $safeName,
                'file_path' => $path,
                'disk' => 'local',
                'extension' => $extension,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function notifyReceiver(InternalMessage $message): void
    {
        $senderName = $message->sender?->name ?: Auth::user()?->name ?: 'مستخدم';
        $reference = $message->reference_label;
        $body = 'وصلتك رسالة داخلية من ' . $senderName;
        if ($reference !== 'بدون ارتباط') {
            $body .= ' بخصوص ' . $reference;
        }

        SystemNotificationService::createForUser(
            (int) $message->receiver_id,
            'رسالة داخلية جديدة',
            $body,
            'internal_message',
            route('internal-messages.show', $message),
            'internal-message:' . $message->id,
            'internal_messages',
            [
                'preference_key' => 'internal_messages.new_message',
                'internal_message_id' => $message->id,
                'sender_id' => $message->sender_id,
                'document_id' => $message->document_id,
                'memo_id' => $message->memo_id,
            ]
        );
    }

    private function authorizeMessageAccess(InternalMessage $message): void
    {
        $user = Auth::user();
        abort_unless($message->isOwnedBy($user) || ($user && method_exists($user, 'hasPermission') && $user->hasPermission('internal_messages.manage')), 403);
    }

    private function ensureAttachmentBelongsToMessage(InternalMessage $message, InternalMessageAttachment $attachment): void
    {
        abort_unless((int) $attachment->internal_message_id === (int) $message->id, 404);
    }

    private function mimeTypeFor(string $extension, ?string $fallback = null): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            default => $fallback ?: 'application/octet-stream',
        };
    }

    private function normalizeText(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?: (string) $value);
    }

    private function normalizeNullableText(?string $value): ?string
    {
        $value = $this->normalizeText($value);
        return $value === '' ? null : $value;
    }
}
