<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MiscBook extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'misc_number',
        'misc_year',
        'misc_sequence',
        'original_number',
        'book_date',
        'subject',
        'category_id',
        'correspondence_direction',
        'nature',
        'sender',
        'receiver',
        'employee_name',
        'authority_name',
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
        'keywords',
        'notes',
        'search_text',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'misc_year' => 'integer',
            'misc_sequence' => 'integer',
            'book_date' => 'date',
            'workflow_submitted_at' => 'datetime',
            'workflow_reviewed_at' => 'datetime',
            'workflow_finalized_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ArchiveCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(MiscBookAttachment::class);
    }

    public function attachmentsWithTrashed()
    {
        return $this->hasMany(MiscBookAttachment::class)->withTrashed();
    }

    public function mainAttachment()
    {
        return $this->hasOne(MiscBookAttachment::class)->where('is_main', true);
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'archived' => 'مؤرشف',
            'cancelled' => 'ملغي',
            default => 'نشط',
        };
    }

    public function getDirectionNameAttribute(): string
    {
        return match ($this->correspondence_direction) {
            'outgoing' => 'صادر',
            'internal' => 'داخلي',
            default => 'وارد',
        };
    }

    public function getNatureNameAttribute(): string
    {
        return match ($this->nature) {
            'military' => 'عسكري',
            'civil' => 'مدني',
            default => 'غير محدد',
        };
    }

    public function getConfidentialityNameAttribute(): string
    {
        return match ($this->confidentiality) {
            'confidential' => 'سري',
            'secret' => 'سري جدًا',
            'top_secret' => 'سري للغاية',
            default => 'عادي',
        };
    }

    public function getPriorityNameAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'عاجل',
            'very_urgent' => 'عاجل جدًا',
            default => 'عادي',
        };
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->book_date?->format('d/m/Y') ?: '';
    }

    public function isWorkflowLocked(): bool
    {
        return ($this->workflow_status ?: 'draft') === 'final_archived';
    }

    public function canBeModifiedBy(?User $user): bool
    {
        if (! $this->isWorkflowLocked()) {
            return true;
        }

        return $user
            && method_exists($user, 'hasPermission')
            && $user->hasPermission('workflow.override');
    }
}
