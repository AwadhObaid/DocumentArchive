<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Memo;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Services\ActivityLogger;
use App\Services\SystemNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    public function submitDocument(Request $request, Document $document)
    {
        $this->authorizeWorkflowPermission('workflow.submit');
        return $this->transition($request, $document, 'submit');
    }

    public function approveDocument(Request $request, Document $document)
    {
        $this->authorizeWorkflowPermission('workflow.approve');
        return $this->transition($request, $document, 'approve');
    }

    public function rejectDocument(Request $request, Document $document)
    {
        $this->authorizeWorkflowPermission('workflow.reject');
        return $this->transition($request, $document, 'reject');
    }

    public function returnDocument(Request $request, Document $document)
    {
        $this->authorizeWorkflowPermission('workflow.reject');
        return $this->transition($request, $document, 'return');
    }

    public function finalizeDocument(Request $request, Document $document)
    {
        $this->authorizeWorkflowPermission('workflow.finalize');
        return $this->transition($request, $document, 'finalize');
    }

    public function reopenDocument(Request $request, Document $document)
    {
        $this->authorizeWorkflowPermission('workflow.override');
        return $this->transition($request, $document, 'reopen');
    }

    public function submitMemo(Request $request, Memo $memo)
    {
        $this->authorizeWorkflowPermission('workflow.submit');
        return $this->transition($request, $memo, 'submit');
    }

    public function approveMemo(Request $request, Memo $memo)
    {
        $this->authorizeWorkflowPermission('workflow.approve');
        return $this->transition($request, $memo, 'approve');
    }

    public function rejectMemo(Request $request, Memo $memo)
    {
        $this->authorizeWorkflowPermission('workflow.reject');
        return $this->transition($request, $memo, 'reject');
    }

    public function returnMemo(Request $request, Memo $memo)
    {
        $this->authorizeWorkflowPermission('workflow.reject');
        return $this->transition($request, $memo, 'return');
    }

    public function finalizeMemo(Request $request, Memo $memo)
    {
        $this->authorizeWorkflowPermission('workflow.finalize');
        return $this->transition($request, $memo, 'finalize');
    }

    public function reopenMemo(Request $request, Memo $memo)
    {
        $this->authorizeWorkflowPermission('workflow.override');
        return $this->transition($request, $memo, 'reopen');
    }

    private function transition(Request $request, Model $record, string $action)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $fromStatus = (string) ($record->workflow_status ?: 'draft');
        $toStatus = $this->targetStatus($action);

        if (! $this->canTransition($fromStatus, $action)) {
            return back()->with('error', 'لا يمكن تنفيذ هذا الإجراء على الحالة الحالية: ' . WorkflowAction::statusName($fromStatus));
        }

        DB::transaction(function () use ($record, $action, $fromStatus, $toStatus, $validated) {
            $updates = [
                'workflow_status' => $toStatus,
                'workflow_note' => $validated['note'] ?? null,
            ];

            if ($action === 'submit') {
                $updates['workflow_submitted_by'] = Auth::id();
                $updates['workflow_submitted_at'] = now();
            }

            if (in_array($action, ['approve', 'reject', 'return'], true)) {
                $updates['workflow_reviewed_by'] = Auth::id();
                $updates['workflow_reviewed_at'] = now();
            }

            if ($action === 'finalize') {
                $updates['workflow_finalized_by'] = Auth::id();
                $updates['workflow_finalized_at'] = now();
                if ($record instanceof Document || $record instanceof Memo) {
                    $updates['status'] = 'archived';
                }
            }

            if ($action === 'reopen') {
                $updates['workflow_finalized_by'] = null;
                $updates['workflow_finalized_at'] = null;
            }

            $record->update($updates);

            $record->workflowActions()->create([
                'action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        $record->refresh();

        $this->logAndNotify($record, $action, $fromStatus, $toStatus, $validated['note'] ?? null);

        return back()->with('success', $this->successMessage($record, $action, $toStatus));
    }

    private function targetStatus(string $action): string
    {
        return match ($action) {
            'submit' => 'under_review',
            'approve' => 'approved',
            'reject' => 'rejected',
            'return' => 'returned_for_edit',
            'finalize' => 'final_archived',
            'reopen' => 'approved',
            default => 'draft',
        };
    }

    private function canTransition(string $fromStatus, string $action): bool
    {
        return match ($action) {
            'submit' => in_array($fromStatus, ['draft', 'rejected', 'returned_for_edit'], true),
            'approve', 'reject', 'return' => $fromStatus === 'under_review',
            'finalize' => $fromStatus === 'approved',
            'reopen' => $fromStatus === 'final_archived',
            default => false,
        };
    }

    private function authorizeWorkflowPermission(string $permission): void
    {
        $user = Auth::user();
        if (! $user || ! method_exists($user, 'hasPermission') || ! $user->hasPermission($permission)) {
            abort(403, 'ليست لديك صلاحية تنفيذ هذا الإجراء.');
        }
    }

    private function logAndNotify(Model $record, string $action, string $fromStatus, string $toStatus, ?string $note): void
    {
        $label = $this->recordLabel($record);
        $title = $this->notificationTitle($record, $action);
        $body = 'تم تغيير حالة الاعتماد من ' . WorkflowAction::statusName($fromStatus) . ' إلى ' . WorkflowAction::statusName($toStatus) . '.';
        if ($note) {
            $body .= ' الملاحظة: ' . $note;
        }

        ActivityLogger::log('workflow.' . $action, $title . ' - ' . $label, $record, [
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);

        $link = $record instanceof Document
            ? route('documents.show', $record)
            : route('memos.show', $record);

        $creatorId = (int) ($record->created_by ?: 0);
        if ($creatorId && $creatorId !== (int) Auth::id()) {
            SystemNotificationService::createForUser($creatorId, $title, $body, 'info', $link, null, 'workflow', [
                'action' => $action,
                'record_type' => $record instanceof Document ? 'document' : 'memo',
                'record_id' => $record->id,
            ]);
        }

        if ($action === 'submit') {
            foreach ($this->workflowReviewerUsers() as $user) {
                if ((int) $user->id === (int) Auth::id()) {
                    continue;
                }
                SystemNotificationService::createForUser($user, $title, $body, 'info', $link, null, 'workflow', [
                    'action' => $action,
                    'record_type' => $record instanceof Document ? 'document' : 'memo',
                    'record_id' => $record->id,
                ]);
            }
        }
    }

    private function workflowReviewerUsers()
    {
        try {
            return User::query()
                ->where(function ($query) {
                    $query->whereNull('is_active')->orWhere('is_active', true);
                })
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('workflow.approve') || $user->hasPermission('workflow.finalize'));
        } catch (\Throwable $exception) {
            report($exception);
            return collect();
        }
    }

    private function notificationTitle(Model $record, string $action): string
    {
        $prefix = $record instanceof Document ? 'كتاب' : 'مذكرة';
        $number = $record instanceof Document ? $record->reference_number : $record->memo_number;

        return match ($action) {
            'submit' => "تم إرسال {$prefix} للمراجعة: {$number}",
            'approve' => "تم اعتماد {$prefix}: {$number}",
            'reject' => "تم رفض {$prefix}: {$number}",
            'return' => "تم إرجاع {$prefix} للتعديل: {$number}",
            'finalize' => "تمت الأرشفة النهائية لـ {$prefix}: {$number}",
            'reopen' => "تمت إعادة فتح {$prefix}: {$number}",
            default => "تحديث اعتماد {$prefix}: {$number}",
        };
    }

    private function recordLabel(Model $record): string
    {
        if ($record instanceof Document) {
            return 'الكتاب رقم ' . $record->reference_number;
        }

        if ($record instanceof Memo) {
            return 'المذكرة رقم ' . $record->memo_number;
        }

        return 'سجل رقم ' . $record->getKey();
    }

    private function successMessage(Model $record, string $action, string $toStatus): string
    {
        $recordName = $record instanceof Document ? 'الكتاب' : 'المذكرة';

        return match ($action) {
            'submit' => "تم إرسال {$recordName} للمراجعة بنجاح.",
            'approve' => "تم اعتماد {$recordName} بنجاح.",
            'reject' => "تم رفض {$recordName}.",
            'return' => "تم إرجاع {$recordName} للتعديل.",
            'finalize' => "تمت الأرشفة النهائية، وأصبح {$recordName} مقفلاً من التعديل.",
            'reopen' => "تمت إعادة فتح {$recordName} للتعديل بصلاحية عليا.",
            default => 'تم تحديث حالة الاعتماد إلى: ' . WorkflowAction::statusName($toStatus),
        };
    }
}
