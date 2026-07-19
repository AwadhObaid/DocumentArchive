<?php

namespace App\Http\Controllers;

use App\Models\AttachmentRelocationRun;
use App\Services\AttachmentRelocationService;
use Illuminate\Http\Request;

class AttachmentRelocationController extends Controller
{
    public function index(Request $request, AttachmentRelocationService $service)
    {
        $this->authorizeTool();

        $limit = max(1, min(5000, (int) $request->integer('limit', 500)));
        $summary = $service->summarize($limit);
        $runs = AttachmentRelocationRun::query()
            ->with('creator')
            ->latest()
            ->limit(10)
            ->get();

        return view('attachments_migration.index', compact('summary', 'runs', 'limit'));
    }

    public function dryRun(Request $request, AttachmentRelocationService $service)
    {
        $this->authorizeTool();

        $limit = max(1, min(5000, (int) $request->integer('limit', 500)));
        $run = $service->dryRun($limit);

        return redirect()
            ->route('attachments-migration.show', $run)
            ->with('success', 'تم إنشاء تقرير فحص المرفقات القديمة بدون نقل فعلي.');
    }

    public function execute(Request $request, AttachmentRelocationService $service)
    {
        $this->authorizeTool();

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'confirm_phrase' => ['required', 'string'],
            'delete_original' => ['nullable', 'boolean'],
        ], [
            'confirm_phrase.required' => 'اكتب عبارة التأكيد قبل التنفيذ.',
        ]);

        if (trim((string) $validated['confirm_phrase']) !== 'تنفيذ النقل') {
            return back()->withErrors(['confirm_phrase' => 'عبارة التأكيد غير صحيحة. اكتب: تنفيذ النقل'])->withInput();
        }

        $run = $service->execute(
            max(1, min(5000, (int) ($validated['limit'] ?? 500))),
            $request->boolean('delete_original')
        );

        return redirect()
            ->route('attachments-migration.show', $run)
            ->with('success', 'تم تنفيذ ترتيب المرفقات القديمة. راجع سجل العملية أدناه.');
    }

    public function show(AttachmentRelocationRun $run)
    {
        $this->authorizeTool();

        $run->load(['creator', 'items.attachment', 'items.document']);

        return view('attachments_migration.show', compact('run'));
    }

    private function authorizeTool(): void
    {
        $user = auth()->user();
        $allowed = $user && method_exists($user, 'hasPermission')
            && ($user->hasPermission('attachments.relocate') || $user->hasPermission('settings.manage'));

        abort_unless($allowed, 403, 'غير مصرح لك باستخدام أداة ترتيب المرفقات القديمة.');
    }
}
