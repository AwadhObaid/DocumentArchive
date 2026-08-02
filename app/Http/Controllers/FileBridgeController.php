<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\FileBridgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileBridgeController extends Controller
{
    public function create(Request $request, FileBridgeService $bridge)
    {
        $this->authorizeBridge();

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        $document = isset($validated['document_id'])
            ? Document::query()->findOrFail((int) $validated['document_id'])
            : null;

        if ($document && method_exists($document, 'canBeModifiedBy')
            && ! $document->canBeModifiedBy($request->user())) {
            abort(403, 'لا يمكن ربط ملف بهذا الكتاب حسب حالته الحالية.');
        }

        $created = $bridge->createRequest(
            $request->user(),
            $document,
            $validated['query'] ?? null,
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        $bridgeRequest = $created['request'];
        $clientToken = $created['client_token'];
        $clientRelativeUrl = route('file-bridge.client.info', ['uuid' => $bridgeRequest->uuid], false);
        $clientUrl = $this->absoluteRequestUrl($request, $clientRelativeUrl);
        $deepLink = 'documentarchive://open?request=' . rawurlencode($clientUrl)
            . '&token=' . rawurlencode($clientToken);

        return response()->json([
            'ok' => true,
            'request_id' => $bridgeRequest->uuid,
            'status' => $bridgeRequest->status,
            'status_url' => route('file-bridge.status', ['uuid' => $bridgeRequest->uuid]),
            'cancel_url' => route('file-bridge.cancel', ['uuid' => $bridgeRequest->uuid]),
            'deep_link' => $deepLink,
            'expires_at' => $bridgeRequest->expires_at?->toIso8601String(),
            'expires_in_seconds' => max(0, now()->diffInSeconds($bridgeRequest->expires_at, false)),
        ], 201, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function status(Request $request, string $uuid, FileBridgeService $bridge)
    {
        $this->authorizeBridge();
        $bridgeRequest = $bridge->statusForUser($uuid, $request->user());

        $payload = [
            'ok' => true,
            'request_id' => $bridgeRequest->uuid,
            'status' => $bridgeRequest->status,
            'message' => $bridge->statusMessage($bridgeRequest),
            'expires_at' => $bridgeRequest->expires_at?->toIso8601String(),
        ];

        if ($bridgeRequest->status === FileBridgeService::STATUS_READY) {
            $payload['file'] = [
                'name' => $bridgeRequest->original_name,
                'extension' => $bridgeRequest->extension,
                'size' => (int) $bridgeRequest->file_size,
                'size_human' => $bridge->formatBytes((int) $bridgeRequest->file_size),
                'client_machine' => $bridgeRequest->client_machine,
            ];
            $payload['selection_token'] = $bridge->selectionToken($bridgeRequest);
        }

        return response()->json($payload, 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function cancel(Request $request, string $uuid, FileBridgeService $bridge)
    {
        $this->authorizeBridge();
        $bridgeRequest = $bridge->cancelForUser($uuid, $request->user());

        return response()->json([
            'ok' => true,
            'status' => $bridgeRequest->status,
            'message' => $bridge->statusMessage($bridgeRequest),
        ], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function clientInfo(Request $request, string $uuid, FileBridgeService $bridge)
    {
        try {
            $bridgeRequest = $bridge->findForClient($uuid, $this->clientToken($request), true);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 410, [
                'Cache-Control' => 'no-store',
            ]);
        }

        $base = route('file-bridge.client.info', ['uuid' => $bridgeRequest->uuid], false);

        return response()->json([
            'ok' => true,
            'request_id' => $bridgeRequest->uuid,
            'status' => $bridgeRequest->status,
            'query' => $bridgeRequest->query ?: $bridgeRequest->reference_number ?: '',
            'reference_number' => $bridgeRequest->reference_number,
            'reference_year' => $bridgeRequest->reference_year,
            'allowed_extensions' => $bridge->allowedExtensions(),
            'max_file_bytes' => $bridge->maxFileBytes(),
            'max_file_mb' => $bridge->maxFileMegabytes(),
            'expires_at' => $bridgeRequest->expires_at?->toIso8601String(),
            'upload_url' => $this->absoluteRequestUrl($request, $base . '/upload'),
            'failure_url' => $this->absoluteRequestUrl($request, $base . '/failure'),
            'system_name' => config('app.name', 'DocumentArchive'),
        ], 200, [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function clientUpload(Request $request, string $uuid, FileBridgeService $bridge)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:' . ($bridge->maxFileMegabytes() * 1024)],
            'original_name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_machine' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $bridgeRequest = $bridge->receiveUpload(
                $uuid,
                $this->clientToken($request),
                $request->file('file'),
                $validated['original_name'],
                $validated['client_name'] ?? null,
                $validated['client_machine'] ?? null
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
                'errors' => method_exists($exception, 'errors') ? $exception->errors() : null,
            ], 422, [
                'Cache-Control' => 'no-store',
            ]);
        }

        return response()->json([
            'ok' => true,
            'status' => $bridgeRequest->status,
            'message' => 'تم رفع الملف إلى النظام بنجاح.',
            'file' => [
                'name' => $bridgeRequest->original_name,
                'size' => (int) $bridgeRequest->file_size,
            ],
        ], 201, [
            'Cache-Control' => 'no-store',
        ]);
    }

    public function clientFailure(Request $request, string $uuid, FileBridgeService $bridge)
    {
        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $bridgeRequest = $bridge->markClientFailure($uuid, $this->clientToken($request), $validated['message'] ?? null);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 410, [
                'Cache-Control' => 'no-store',
            ]);
        }

        return response()->json([
            'ok' => true,
            'status' => $bridgeRequest->status,
        ], 200, [
            'Cache-Control' => 'no-store',
        ]);
    }

    public function downloadClient(): BinaryFileResponse
    {
        $this->authorizeBridge();
        $path = public_path('downloads/DocumentArchive_File_Bridge_Windows_V94_2.zip');

        abort_unless(is_file($path), 404, 'حزمة File Bridge غير موجودة على السيرفر.');

        return response()->download(
            $path,
            'DocumentArchive_File_Bridge_Windows_V94_2.zip',
            ['Content-Type' => 'application/zip']
        );
    }

    private function authorizeBridge(): void
    {
        $user = auth()->user();

        $allowed = $user
            && method_exists($user, 'hasPermission')
            && (
                $user->hasPermission('documents.create')
                || $user->hasPermission('documents.edit')
                || $user->hasPermission('settings.manage')
            );

        abort_unless($allowed, 403, 'غير مصرح لك باستخدام File Bridge.');
    }

    private function clientToken(Request $request): string
    {
        $token = trim((string) $request->header('X-File-Bridge-Token', ''));

        abort_if($token === '' || strlen($token) > 256, 401, 'رمز File Bridge مفقود أو غير صالح.');

        return $token;
    }

    private function absoluteRequestUrl(Request $request, string $relativeUrl): string
    {
        if (Str::startsWith($relativeUrl, ['http://', 'https://'])) {
            return $relativeUrl;
        }

        return rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/')
            . '/' . ltrim($relativeUrl, '/');
    }
}
