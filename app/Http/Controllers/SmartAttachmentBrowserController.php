<?php

namespace App\Http\Controllers;

use App\Services\SmartAttachmentBrowserService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;

class SmartAttachmentBrowserController extends Controller
{
    public function sources(Request $request, SmartAttachmentBrowserService $browser)
    {
        $this->authorizeBrowser();

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2200'],
        ]);

        return response()->json([
            'ok' => true,
            'enabled' => $browser->enabled(),
            'recursive_default' => $browser->recursiveByDefault(),
            'max_file_mb' => $browser->maxFileMegabytes(),
            'sources' => $browser->configuredSources(
                isset($validated['year']) ? (int) $validated['year'] : null
            ),
        ], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function search(Request $request, SmartAttachmentBrowserService $browser)
    {
        $this->authorizeBrowser();

        $validated = $request->validate([
            'source' => ['required', 'string', 'in:outgoing,incoming,general'],
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'recursive' => ['nullable', 'boolean'],
        ], [
            'source.required' => 'اختر مصدر البحث.',
            'q.required' => 'اكتب رقم الكتاب أو عبارة البحث.',
            'q.min' => 'عبارة البحث قصيرة جداً.',
        ]);

        try {
            $result = $browser->search(
                $validated['source'],
                $validated['q'],
                isset($validated['year']) ? (int) $validated['year'] : null,
                array_key_exists('recursive', $validated)
                    ? filter_var($validated['recursive'], FILTER_VALIDATE_BOOLEAN)
                    : null
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422, [
                'Cache-Control' => 'no-store, private',
            ]);
        }

        $items = array_map(function (array $item): array {
            $item['preview_url'] = route('smart-attachment-browser.preview', [
                'token' => $item['token'],
            ]);

            return $item;
        }, $result['items']);

        return response()->json([
            'ok' => true,
            'source' => $result['source'],
            'root' => $result['root'],
            'query' => $result['query'],
            'recursive' => $result['recursive'],
            'limit' => $result['limit'],
            'timed_out' => $result['timed_out'],
            'items' => $items,
        ], 200, [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function preview(Request $request, SmartAttachmentBrowserService $browser)
    {
        $this->authorizeBrowser();

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:12000'],
        ]);

        $file = $browser->resolveToken($validated['token']);
        $mimeType = $browser->mimeType($file['absolute_path'], $file['extension']);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $file['name'],
            'attachment.' . ($file['extension'] ?: 'bin')
        );

        return response()->file($file['absolute_path'], [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeBrowser(): void
    {
        $user = auth()->user();

        $allowed = $user
            && method_exists($user, 'hasPermission')
            && (
                $user->hasPermission('documents.create')
                || $user->hasPermission('documents.edit')
                || $user->hasPermission('settings.manage')
            );

        abort_unless($allowed, 403, 'غير مصرح لك بالبحث في مصادر المرفقات.');
    }
}
