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
        'description',
        'sender',
        'receiver',

        'department_id',
        'document_type_id',
        'created_by',

        'status',
        'confidentiality',
        'priority',

        'print_title',
        'print_top_mm',
        'print_left_mm',

        'search_text',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reference_year' => 'integer',
            'reference_sequence' => 'integer',
            'reference_date' => 'date',
            'deleted_at' => 'datetime',

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(DocumentAttachment::class);
    }

    public function mainAttachment()
    {
        return $this->hasOne(DocumentAttachment::class)->where('is_main', true);
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
