@php
    /** @var \Illuminate\Database\Eloquent\Model $record */
    $workflowType = $type ?? ($record instanceof \App\Models\Document ? 'document' : 'memo');
    $workflowStatus = $record->workflow_status ?: 'draft';
    $workflowStatusName = $record->workflow_status_name ?? \App\Models\WorkflowAction::statusName($workflowStatus);
    $workflowActions = collect($record->workflowActions ?? []);
    $workflowUser = auth()->user();
    $canSubmitWorkflow = $workflowUser?->hasPermission('workflow.submit');
    $canApproveWorkflow = $workflowUser?->hasPermission('workflow.approve');
    $canRejectWorkflow = $workflowUser?->hasPermission('workflow.reject');
    $canFinalizeWorkflow = $workflowUser?->hasPermission('workflow.finalize');
    $canOverrideWorkflow = $workflowUser?->hasPermission('workflow.override');

    $routePrefix = $workflowType === 'memo' ? 'memos.workflow' : 'documents.workflow';
    $routeParam = $workflowType === 'memo' ? ['memo' => $record] : ['document' => $record];

    $actionButton = function (string $routeName, string $label, string $class, string $placeholder = 'ملاحظة اختيارية') use ($routePrefix, $routeParam) {
        return [
            'route' => route($routePrefix . '.' . $routeName, $routeParam),
            'label' => $label,
            'class' => $class,
            'placeholder' => $placeholder,
        ];
    };

    $availableWorkflowActions = [];
    if (in_array($workflowStatus, ['draft', 'rejected', 'returned_for_edit'], true) && $canSubmitWorkflow) {
        $availableWorkflowActions[] = $actionButton('submit', 'إرسال للمراجعة', 'btn btn-warning', 'ملاحظة للمراجع اختيارية');
    }
    if ($workflowStatus === 'under_review' && $canApproveWorkflow) {
        $availableWorkflowActions[] = $actionButton('approve', 'اعتماد', 'btn btn-success', 'ملاحظة الاعتماد اختيارية');
    }
    if ($workflowStatus === 'under_review' && $canRejectWorkflow) {
        $availableWorkflowActions[] = $actionButton('return', 'إرجاع للتعديل', 'btn btn-secondary', 'سبب الإرجاع للتعديل');
        $availableWorkflowActions[] = $actionButton('reject', 'رفض', 'btn btn-danger', 'سبب الرفض');
    }
    if ($workflowStatus === 'approved' && $canFinalizeWorkflow) {
        $availableWorkflowActions[] = $actionButton('finalize', 'أرشفة نهائية', 'btn btn-primary', 'ملاحظة الأرشفة النهائية اختيارية');
    }
    if ($workflowStatus === 'final_archived' && $canOverrideWorkflow) {
        $availableWorkflowActions[] = $actionButton('reopen', 'إعادة فتح بصلاحية عليا', 'btn btn-danger', 'سبب إعادة فتح السجل');
    }
@endphp

<div class="card workflow-card mt-4 no-print">
    <div class="workflow-header">
        <div>
            <h2 class="workflow-title">✅ مسار الاعتماد والأرشفة النهائية</h2>
            <p style="margin:8px 0 0;color:var(--muted,#64748b);">تابع حالة المراجعة والاعتماد ومنع التعديل بعد الأرشفة النهائية.</p>
        </div>
        <span class="workflow-status-badge workflow-status-{{ $workflowStatus }}">{{ $workflowStatusName }}</span>
    </div>

    @if($workflowStatus === 'final_archived')
        <div class="workflow-lock-note">🔒 هذا السجل مؤرشف نهائيًا. لا يمكن تعديله أو حذفه إلا بصلاحية عليا.</div>
    @endif

    <div class="workflow-meta-grid">
        <div class="workflow-meta-item">
            <span>أرسل للمراجعة بواسطة</span>
            <strong>{{ $record->workflowSubmitter?->name ?: '-' }}</strong>
            <small>{{ optional($record->workflow_submitted_at)->format('Y-m-d H:i') }}</small>
        </div>
        <div class="workflow-meta-item">
            <span>آخر مراجعة بواسطة</span>
            <strong>{{ $record->workflowReviewer?->name ?: '-' }}</strong>
            <small>{{ optional($record->workflow_reviewed_at)->format('Y-m-d H:i') }}</small>
        </div>
        <div class="workflow-meta-item">
            <span>الأرشفة النهائية بواسطة</span>
            <strong>{{ $record->workflowFinalizer?->name ?: '-' }}</strong>
            <small>{{ optional($record->workflow_finalized_at)->format('Y-m-d H:i') }}</small>
        </div>
        <div class="workflow-meta-item">
            <span>آخر ملاحظة</span>
            <strong>{{ $record->workflow_note ?: '-' }}</strong>
        </div>
    </div>

    @if(count($availableWorkflowActions))
        <div class="workflow-actions">
            @foreach($availableWorkflowActions as $workflowAction)
                <form method="POST" action="{{ $workflowAction['route'] }}" onsubmit="return confirm('تأكيد تنفيذ الإجراء: {{ $workflowAction['label'] }}؟');">
                    @csrf
                    <input type="text" name="note" placeholder="{{ $workflowAction['placeholder'] }}">
                    <button type="submit" class="{{ $workflowAction['class'] }}">{{ $workflowAction['label'] }}</button>
                </form>
            @endforeach
        </div>
    @else
        <div class="workflow-lock-note" style="background:#f8fafc;color:#475569;border-color:#e2e8f0;">لا توجد إجراءات اعتماد متاحة لحسابك أو للحالة الحالية.</div>
    @endif

    <div class="workflow-history">
        <strong>سجل قرارات الاعتماد</strong>
        @if($workflowActions->count())
            <div class="workflow-history-list">
                @foreach($workflowActions->take(8) as $wfAction)
                    <div class="workflow-history-item">
                        <div><strong>{{ $wfAction->action_name }}</strong> من <strong>{{ $wfAction->from_status_name }}</strong> إلى <strong>{{ $wfAction->to_status_name }}</strong></div>
                        <div class="small">بواسطة: {{ $wfAction->actor?->name ?: '-' }} — {{ optional($wfAction->created_at)->format('Y-m-d H:i') }}</div>
                        @if($wfAction->note)
                            <div style="margin-top:5px;">{{ $wfAction->note }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <p style="color:var(--muted,#64748b);margin:8px 0 0;">لا توجد قرارات اعتماد مسجلة بعد.</p>
        @endif
    </div>
</div>
