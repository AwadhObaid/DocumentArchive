<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmartReportRun extends Model
{
    protected $fillable = [
        'user_id',
        'date_from',
        'date_to',
        'report_type',
        'title',
        'status',
        'model',
        'prompt',
        'payload_json',
        'result_text',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'payload_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'مكتمل',
            'failed' => 'فشل',
            default => 'قيد المعالجة',
        };
    }

    public function getReportTypeNameAttribute(): string
    {
        return \App\Http\Controllers\SmartReportController::reportTypes()[$this->report_type] ?? $this->report_type;
    }
}
