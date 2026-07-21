<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_number',
        'reference_year',
        'reference_sequence',
        'reference_date',

        'main_policy_number',
        'sub_policy_number',

        'title',
        'subject',
        'book_subject_id',
        'attachment_company_name',
        'attachment_category_name',
        'description',
        'sender',
        'receiver',

        'department_id',
        'document_type_id',
        'created_by',

        'status',
        'workflow_status',
        'workflow_note',
        'workflow_submitted_by',
        'workflow_submitted_at',
        'workflow_reviewed_by',
        'workflow_reviewed_at',
        'workflow_finalized_by',
        'workflow_finalized_at',
        'confidentiality',
        'priority',

        'print_title',
        'print_top_mm',
        'print_left_mm',

        'search_text',
        'notes',
        'legacy_source',
        'legacy_record_id',
        'legacy_user_name',
        'legacy_archive_folder',
        'legacy_sader_id',
        'legacy_original_sader_id',
    ];

    protected function casts(): array
    {
        return [
            'reference_year' => 'integer',
            'reference_sequence' => 'integer',
            'reference_date' => 'date',
            'deleted_at' => 'datetime',
            'workflow_submitted_at' => 'datetime',
            'workflow_reviewed_at' => 'datetime',
            'workflow_finalized_at' => 'datetime',

            'print_top_mm' => 'decimal:2',
            'print_left_mm' => 'decimal:2',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function bookSubject()
    {
        return $this->belongsTo(BookSubject::class);
    }

    public function attachmentCompany()
    {
        return $this->belongsTo(BookAttachmentCompany::class, 'book_attachment_company_id');
    }

    public function attachmentOperation()
    {
        return $this->belongsTo(BookAttachmentOperation::class, 'book_attachment_operation_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    public function attachmentsWithTrashed()
    {
        return $this->hasMany(DocumentAttachment::class)->withTrashed();
    }

    public function mainAttachment()
    {
        return $this->hasOne(DocumentAttachment::class)->where('is_main', true);
    }

    public function emailMessages()
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }


    public function internalMessages()
    {
        return $this->hasMany(InternalMessage::class);
    }


    public function sharedAttachmentLinks()
    {
        return $this->hasMany(SharedAttachmentLink::class);
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

    public function getFormattedDateAttribute(): string
    {
        return $this->reference_date
            ? $this->reference_date->format('d/m/Y')
            : '';
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'registered' => 'مسجل',
            'archived' => 'مؤرشف',
            'active' => 'نشط',
            'cancelled' => 'ملغي',
            default => 'مسجل',
        };
    }

    public function getConfidentialityNameAttribute(): string
    {
        return match ($this->confidentiality) {
            'normal' => 'عادي',
            'confidential' => 'سري',
            'very_confidential' => 'سري للغاية',
            default => 'عادي',
        };
    }

    public function getPriorityNameAttribute(): string
    {
        return match ($this->priority) {
            'normal' => 'عادي',
            'high' => 'هام',
            'urgent' => 'عاجل',
            default => 'عادي',
        };
    }
}
