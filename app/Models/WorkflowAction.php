<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAction extends Model
{
    protected $fillable = [
        'workflowable_type',
        'workflowable_id',
        'action',
        'from_status',
        'to_status',
        'note',
        'created_by',
    ];

    public function workflowable()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActionNameAttribute(): string
    {
        return match ($this->action) {
            'submit' => 'إرسال للمراجعة',
            'approve' => 'اعتماد',
            'reject' => 'رفض',
            'return' => 'إرجاع للتعديل',
            'finalize' => 'أرشفة نهائية',
            'reopen' => 'إعادة فتح',
            default => $this->action,
        };
    }

    public function getFromStatusNameAttribute(): string
    {
        return self::statusName($this->from_status);
    }

    public function getToStatusNameAttribute(): string
    {
        return self::statusName($this->to_status);
    }

    public static function statusName(?string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة',
            'under_review' => 'قيد المراجعة',
            'approved' => 'معتمد',
            'rejected' => 'مرفوض',
            'returned_for_edit' => 'مرجع للتعديل',
            'final_archived' => 'مؤرشف نهائيًا',
            null, '' => '-',
            default => (string) $status,
        };
    }
}
