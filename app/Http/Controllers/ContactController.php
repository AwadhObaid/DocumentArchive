<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $query = Contact::query()->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('organization', 'like', '%' . $search . '%')
                    ->orWhere('contact_person', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('whatsapp_number', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $contacts = $query->paginate(15)->withQueryString();

        $summary = [
            'total' => Contact::query()->count(),
            'active' => Contact::query()->where('is_active', true)->count(),
            'email' => Contact::query()->whereNotNull('email')->where('email', '<>', '')->count(),
            'whatsapp' => Contact::query()->whereNotNull('whatsapp_number')->where('whatsapp_number', '<>', '')->count(),
        ];

        return view('contacts.index', compact('contacts', 'summary'));
    }

    public function create(): View
    {
        $contact = new Contact([
            'type' => 'external',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return view('contacts.create', compact('contact'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $contact = Contact::create($validated);

        ActivityLogger::log('contact.created', 'تمت إضافة جهة اتصال: ' . $contact->name, $contact, [
            'contact_id' => $contact->id,
        ]);

        return redirect()->route('contacts.index')->with('success', 'تمت إضافة جهة الاتصال بنجاح.');
    }

    public function edit(Contact $contact): View
    {
        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['updated_by'] = Auth::id();

        $contact->update($validated);

        ActivityLogger::log('contact.updated', 'تم تعديل جهة اتصال: ' . $contact->name, $contact, [
            'contact_id' => $contact->id,
        ]);

        return redirect()->route('contacts.index')->with('success', 'تم تحديث جهة الاتصال بنجاح.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $name = $contact->name;
        $contact->delete();

        ActivityLogger::log('contact.deleted', 'تم حذف جهة اتصال: ' . $name, null, [
            'contact_name' => $name,
        ]);

        return redirect()->route('contacts.index')->with('success', 'تم حذف جهة الاتصال بنجاح.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'organization' => ['nullable', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'type' => ['required', Rule::in(['internal', 'external', 'government', 'company', 'department', 'other'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['email']) && empty($validated['whatsapp_number']) && empty($validated['phone'])) {
            throw ValidationException::withMessages([
                'email' => 'يجب إدخال بريد إلكتروني أو رقم واتساب أو رقم هاتف واحد على الأقل.',
            ]);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }
}
