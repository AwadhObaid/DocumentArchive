<?php

namespace App\Http\Controllers;

use App\Models\FormLink;
use Illuminate\Http\Request;
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
