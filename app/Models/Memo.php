<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Memo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'memo_number',
        'memo_sequence',
        'memo_date',
        'subject',
        'description',
        'department_id',
        'sender',
        'receiver',
        'status',
        'workflow_status',
        'workflow_note',
        'workflow_submitted_by',
        'workflow_submitted_at',
        'workflow_reviewed_by',
        'workflow_reviewed_at',
        'workflow_finalized_by',
        'workflow_finalized_at',
        'notes',
        'search_text',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'memo_sequence' => 'integer',
            'memo_date' => 'date',
            'deleted_at' => 'datetime',
            'workflow_submitted_at' => 'datetime',
            'workflow_reviewed_at' => 'datetime',
            'workflow_finalized_at' => 'datetime',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(MemoAttachment::class);
    }

    public function mainAttachment()
    {
        return $this->hasOne(MemoAttachment::class)->where('is_main', true);
    }

    public function internalMessages()
    {
        return $this->hasMany(InternalMessage::class);
    }


    public function workflowActions()
    {
        return $this->morphMany(WorkflowAction::class, 'workflowable')->latest();
    }

    public function workflowSubmitter()
    {
        return $this->belongsTo(User::class, 'workflow_submitted_by');
    }

    public function workflowReviewer()
    {
        return $this->belongsTo(User::class, 'workflow_reviewed_by');
    }

    public function workflowFinalizer()
    {
        return $this->belongsTo(User::class, 'workflow_finalized_by');
    }

    public function getWorkflowStatusNameAttribute(): string
    {
        return WorkflowAction::statusName($this->workflow_status ?: 'draft');
    }

    public function isWorkflowFinalized(): bool
    {
        return ($this->workflow_status ?: 'draft') === 'final_archived';
    }

    public function isWorkflowLocked(): bool
    {
        return $this->isWorkflowFinalized();
    }

    public function canBeModifiedBy(?User $user): bool
    {
        if (! $this->isWorkflowLocked()) {
            return true;
        }

        return $user && method_exists($user, 'hasPermission') && $user->hasPermission('workflow.override');
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'archived' => 'مؤرشفة',
            'cancelled' => 'ملغاة',
            default => 'نشطة',
        };
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->memo_date ? $this->memo_date->format('d/m/Y') : '';
    }
}
