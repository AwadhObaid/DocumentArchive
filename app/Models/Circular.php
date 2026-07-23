<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Circular extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'circular_number',
        'circular_year',
        'circular_sequence',
        'original_number',
        'circular_date',
        'subject',
        'category_id',
        'issuing_entity',
        'scope',
        'effective_date',
        'expiry_date',
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
            'circular_year' => 'integer',
            'circular_sequence' => 'integer',
            'circular_date' => 'date',
            'effective_date' => 'date',
            'expiry_date' => 'date',
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
        return $this->hasMany(CircularAttachment::class);
    }

    public function attachmentsWithTrashed()
    {
        return $this->hasMany(CircularAttachment::class)->withTrashed();
    }

    public function mainAttachment()
    {
        return $this->hasOne(CircularAttachment::class)->where('is_main', true);
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'expired' => 'منتهي',
            'cancelled' => 'ملغي',
            'archived' => 'مؤرشف',
            default => 'ساري',
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
        return $this->circular_date?->format('d/m/Y') ?: '';
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
