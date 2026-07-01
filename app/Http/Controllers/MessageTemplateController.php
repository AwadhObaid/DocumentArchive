<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MessageTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $query = MessageTemplate::query()->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhere('subject_template', 'like', '%' . $search . '%')
                    ->orWhere('body_template', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $templates = $query->paginate(15)->withQueryString();

        $summary = [
            'total' => MessageTemplate::query()->count(),
            'active' => MessageTemplate::query()->where('is_active', true)->count(),
            'email' => MessageTemplate::query()->whereIn('channel', ['email', 'both'])->count(),
            'whatsapp' => MessageTemplate::query()->whereIn('channel', ['whatsapp', 'both'])->count(),
        ];

        return view('message-templates.index', compact('templates', 'summary'));
    }

    public function create(): View
    {
        $template = new MessageTemplate([
            'channel' => 'both',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ]);
        $variables = MessageTemplate::availableVariables();

        return view('message-templates.create', compact('template', 'variables'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $template = DB::transaction(function () use ($validated) {
            $template = MessageTemplate::create($validated);
            $this->syncDefault($template);
            return $template;
        });

        ActivityLogger::log('message_template.created', 'تمت إضافة قالب رسالة: ' . $template->name, $template, [
            'message_template_id' => $template->id,
        ]);

        return redirect()->route('message-templates.index')->with('success', 'تمت إضافة قالب الرسالة بنجاح.');
    }

    public function edit(MessageTemplate $messageTemplate): View
    {
        $template = $messageTemplate;
        $variables = MessageTemplate::availableVariables();

        return view('message-templates.edit', compact('template', 'variables'));
    }

    public function update(Request $request, MessageTemplate $messageTemplate): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['updated_by'] = Auth::id();

        DB::transaction(function () use ($messageTemplate, $validated) {
            $messageTemplate->update($validated);
            $this->syncDefault($messageTemplate);
        });

        ActivityLogger::log('message_template.updated', 'تم تعديل قالب رسالة: ' . $messageTemplate->name, $messageTemplate, [
            'message_template_id' => $messageTemplate->id,
        ]);

        return redirect()->route('message-templates.index')->with('success', 'تم تحديث قالب الرسالة بنجاح.');
    }

    public function destroy(MessageTemplate $messageTemplate): RedirectResponse
    {
        $name = $messageTemplate->name;
        $messageTemplate->delete();

        ActivityLogger::log('message_template.deleted', 'تم حذف قالب رسالة: ' . $name, null, [
            'message_template_name' => $name,
        ]);

        return redirect()->route('message-templates.index')->with('success', 'تم حذف قالب الرسالة بنجاح.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'channel' => ['required', Rule::in(['email', 'whatsapp', 'both'])],
            'subject_template' => ['nullable', 'string', 'max:255'],
            'body_template' => ['required', 'string', 'max:12000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_default'] = $request->boolean('is_default');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function syncDefault(MessageTemplate $template): void
    {
        if (!$template->is_default) {
            return;
        }

        $query = MessageTemplate::query()->where('id', '<>', $template->id);

        if ($template->channel === 'both') {
            $query->whereIn('channel', ['email', 'whatsapp', 'both']);
        } else {
            $query->whereIn('channel', [$template->channel, 'both']);
        }

        $query->update(['is_default' => false]);
    }
}
