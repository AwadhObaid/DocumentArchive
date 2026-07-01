<?php

namespace App\Http\Controllers;

use App\Models\FormLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FormLinkController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => (string) $request->input('status', 'all'),
            'category' => trim((string) $request->input('category', '')),
            'sort' => (string) $request->input('sort', 'order'),
        ];

        if (! in_array($filters['status'], ['all', 'active', 'inactive'], true)) {
            $filters['status'] = 'all';
        }

        if (! in_array($filters['sort'], ['order', 'latest', 'oldest', 'title'], true)) {
            $filters['sort'] = 'order';
        }

        $canManage = (bool) $request->user()?->hasPermission('form_links.manage');

        $query = FormLink::query();

        if (! $canManage) {
            $query->where('is_active', true);
            $filters['status'] = 'active';
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($innerQuery) use ($q) {
                $innerQuery->where('title', 'like', '%' . $q . '%')
                    ->orWhere('url', 'like', '%' . $q . '%')
                    ->orWhere('category', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%');
            });
        }

        if ($canManage) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }

        match ($filters['sort']) {
            'latest' => $query->orderByDesc('id'),
            'oldest' => $query->orderBy('id'),
            'title' => $query->orderBy('title'),
            default => $query->ordered(),
        };

        $formLinks = $query->paginate(15)->withQueryString();

        $categoriesQuery = FormLink::query();

        if (! $canManage) {
            $categoriesQuery->where('is_active', true);
        }

        $categories = $categoriesQuery
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->orderBy('category')
            ->pluck('category')
            ->unique()
            ->values();

        $stats = [
            'total' => $canManage ? FormLink::query()->count() : FormLink::query()->where('is_active', true)->count(),
            'active' => FormLink::query()->where('is_active', true)->count(),
            'inactive' => $canManage ? FormLink::query()->where('is_active', false)->count() : 0,
            'categories' => $categories->count(),
        ];

        return view('form-links.index', compact('formLinks', 'filters', 'categories', 'stats', 'canManage'));
    }

    public function create()
    {
        return view('form-links.create', [
            'formLink' => new FormLink([
                'icon' => '📝',
                'sort_order' => 0,
                'is_active' => true,
                'opens_new_tab' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);

        $validated['created_by'] = $request->user()?->id;
        $validated['updated_by'] = $request->user()?->id;

        FormLink::create($validated);

        return redirect()
            ->route('form-links.index')
            ->with('success', 'تمت إضافة النموذج بنجاح.');
    }

    public function edit(FormLink $formLink)
    {
        return view('form-links.edit', compact('formLink'));
    }

    public function update(Request $request, FormLink $formLink)
    {
        $validated = $this->validatedData($request);
        $validated['updated_by'] = $request->user()?->id;

        $formLink->update($validated);

        return redirect()
            ->route('form-links.index')
            ->with('success', 'تم تحديث النموذج بنجاح.');
    }

    public function destroy(FormLink $formLink)
    {
        $formLink->delete();

        return redirect()
            ->route('form-links.index')
            ->with('success', 'تم حذف النموذج بنجاح.');
    }

    public function print(Request $request, FormLink $formLink)
    {
        $canManage = (bool) $request->user()?->hasPermission('form_links.manage');

        if (! $formLink->is_active && ! $canManage) {
            abort(404);
        }

        $scale = max(82, min(100, (int) $request->integer('scale', 94)));
        $margin = max(0, min(12, (int) $request->integer('margin', 3)));
        $sourceUrl = trim((string) $formLink->url);
        $printableHtml = null;
        $fetchError = null;

        if (! $this->isExternalHttpUrl($sourceUrl)) {
            $fetchError = 'الطباعة المحسّنة متاحة للروابط الخارجية التي تبدأ بـ http أو https فقط.';
        } elseif (! $this->isSafeExternalUrl($sourceUrl)) {
            $fetchError = 'تعذر تجهيز هذا الرابط للطباعة المحسّنة لأسباب أمنية.';
        } else {
            try {
                $response = Http::timeout(25)
                    ->connectTimeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome Safari/537.36 DocumentArchivePrintOptimizer/1.0',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'ar,en;q=0.8',
                    ])
                    ->get($sourceUrl);

                if (! $response->successful()) {
                    $fetchError = 'تعذر تحميل النموذج من المصدر الخارجي. رمز الاستجابة: ' . $response->status();
                } else {
                    $contentType = (string) $response->header('Content-Type', '');
                    $html = $this->decodeRemoteHtml($response->body(), $contentType);
                    $printableHtml = $this->preparePrintableHtml($html, $sourceUrl, $scale, $margin);
                }
            } catch (\Throwable $exception) {
                $fetchError = 'تعذر الاتصال بالمصدر الخارجي حالياً: ' . $exception->getMessage();
            }
        }

        return view('form-links.print', compact('formLink', 'sourceUrl', 'scale', 'margin', 'printableHtml', 'fetchError'));
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
            'opens_new_tab' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'اسم النموذج مطلوب.',
            'url.required' => 'رابط النموذج مطلوب.',
            'url.max' => 'رابط النموذج طويل جداً.',
            'sort_order.integer' => 'ترتيب العرض يجب أن يكون رقماً صحيحاً.',
        ]);

        $validated['title'] = $this->normalizeText($validated['title']);
        $validated['url'] = trim($validated['url']);
        $validated['category'] = $this->normalizeNullableText($validated['category'] ?? null);
        $validated['description'] = $this->normalizeNullableText($validated['description'] ?? null);
        $validated['icon'] = $this->normalizeNullableText($validated['icon'] ?? null) ?: '📝';
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['opens_new_tab'] = $request->boolean('opens_new_tab');

        if (! $this->isValidLink($validated['url'])) {
            throw ValidationException::withMessages([
                'url' => 'الرابط غير صحيح. استخدم رابطاً خارجياً يبدأ بـ http:// أو https:// أو مساراً داخلياً يبدأ بـ /.',
            ]);
        }

        return $validated;
    }

    private function isValidLink(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function isExternalHttpUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function isSafeExternalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return false;
        }

        if (preg_match('/(^|\.)local$/i', $host)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isPrivateOrReservedIp($host);
        }

        return true;
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function decodeRemoteHtml(string $html, string $contentType): string
    {
        $encoding = $this->detectRemoteHtmlEncoding($html, $contentType);
        $candidates = array_values(array_unique(array_filter([
            $encoding,
            'Windows-1256',
            'CP1256',
            'ISO-8859-6',
        ])));

        foreach ($candidates as $candidateEncoding) {
            $normalizedEncoding = $this->normalizeEncodingName((string) $candidateEncoding);

            if ($this->isUtf8Encoding($normalizedEncoding)) {
                return $html;
            }

            $converted = $this->convertHtmlToUtf8($html, $normalizedEncoding);

            if (is_string($converted) && $converted !== '' && $this->looksLikeUtf8($converted)) {
                return $converted;
            }
        }

        return $html;
    }

    private function detectRemoteHtmlEncoding(string $html, string $contentType): ?string
    {
        if (preg_match('/charset\s*=\s*["\']?([^;"\'\s>]+)/i', $contentType, $matches)) {
            return $this->normalizeEncodingName($matches[1]);
        }

        if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>]+)/i', $html, $matches)) {
            return $this->normalizeEncodingName($matches[1]);
        }

        if (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\']*charset\s*=\s*([^;"\'\s>]+)/i', $html, $matches)) {
            return $this->normalizeEncodingName($matches[1]);
        }

        return null;
    }

    private function normalizeEncodingName(?string $encoding): ?string
    {
        $encoding = trim((string) $encoding, " \t\n\r\0\x0B\"'");

        if ($encoding === '') {
            return null;
        }

        $key = strtoupper(str_replace(['_', ' '], '-', $encoding));

        return match ($key) {
            'UTF8', 'UTF-8' => 'UTF-8',
            'WINDOWS1256', 'WINDOWS-1256', 'WIN1256', 'WIN-1256', 'CP-1256', 'CP1256' => 'Windows-1256',
            'ISO8859-6', 'ISO-8859-6' => 'ISO-8859-6',
            default => $encoding,
        };
    }

    private function isUtf8Encoding(?string $encoding): bool
    {
        return in_array(strtoupper((string) $encoding), ['UTF-8', 'UTF8'], true);
    }

    private function convertHtmlToUtf8(string $html, string $encoding): ?string
    {
        foreach ($this->iconvEncodingCandidates($encoding) as $candidateEncoding) {
            if (function_exists('iconv')) {
                try {
                    $converted = @iconv($candidateEncoding, 'UTF-8//IGNORE', $html);
                } catch (\Throwable) {
                    $converted = false;
                }

                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }
        }

        if (function_exists('mb_convert_encoding') && function_exists('mb_list_encodings')) {
            $supportedEncodings = array_map('strtoupper', mb_list_encodings());

            foreach ($this->mbEncodingCandidates($encoding) as $candidateEncoding) {
                if (! in_array(strtoupper($candidateEncoding), $supportedEncodings, true)) {
                    continue;
                }

                try {
                    $converted = mb_convert_encoding($html, 'UTF-8', $candidateEncoding);
                } catch (\Throwable) {
                    $converted = null;
                }

                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            }
        }

        return null;
    }

    private function iconvEncodingCandidates(string $encoding): array
    {
        $normalized = $this->normalizeEncodingName($encoding) ?: $encoding;

        $candidates = [$normalized];

        if ($normalized === 'Windows-1256') {
            $candidates[] = 'CP1256';
            $candidates[] = 'WINDOWS-1256';
        }

        return array_values(array_unique($candidates));
    }

    private function mbEncodingCandidates(string $encoding): array
    {
        $normalized = $this->normalizeEncodingName($encoding) ?: $encoding;

        $candidates = [$normalized];

        if ($normalized === 'Windows-1256') {
            $candidates[] = 'CP1256';
            $candidates[] = 'Windows-1256';
            $candidates[] = 'WINDOWS-1256';
        }

        return array_values(array_unique($candidates));
    }

    private function looksLikeUtf8(string $value): bool
    {
        return ! function_exists('mb_check_encoding') || mb_check_encoding($value, 'UTF-8');
    }

    private function preparePrintableHtml(string $html, string $sourceUrl, int $scale, int $margin): string
    {
        $baseHref = $this->baseHrefFor($sourceUrl);
        $scaleRatio = number_format($scale / 100, 2, '.', '');
        $widthPercent = number_format(100 / ($scale / 100), 3, '.', '');

        $optimizer = <<<HTML
<base href="{$this->escapeHtml($baseHref)}">
<meta charset="utf-8">
<style id="document-archive-form-print-optimizer">
    @page {
        size: A4 portrait;
        margin: {$margin}mm;
    }

    html {
        background: #ffffff !important;
        zoom: {$scaleRatio};
    }

    body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 auto !important;
        padding: 0 !important;
        width: {$widthPercent}% !important;
        max-width: none !important;
        overflow: visible !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    table, tbody, thead, tfoot, tr, td, th, div, section, article {
        page-break-inside: auto;
    }

    img, svg, canvas, object, embed, iframe, table {
        max-width: 100% !important;
    }

    input, textarea, select {
        border-color: #000 !important;
    }

    .no-print,
    .print-hidden,
    button,
    input[type="button"],
    input[type="submit"] {
        display: none !important;
    }

    @media print {
        html, body {
            background: #ffffff !important;
            overflow: visible !important;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>
HTML;

        if (preg_match('/<head[^>]*>/i', $html)) {
            return preg_replace('/<head[^>]*>/i', '$0' . "\n" . $optimizer, $html, 1) ?: ($optimizer . $html);
        }

        return '<!doctype html><html lang="ar" dir="rtl"><head>' . $optimizer . '</head><body>' . $html . '</body></html>';
    }

    private function baseHrefFor(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        if ($directory === '' || $directory === '.') {
            $directory = '';
        }

        return $scheme . '://' . $host . $port . $directory . '/';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
