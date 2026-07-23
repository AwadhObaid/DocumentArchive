<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyCircularMiscImportItem extends Model
{
    protected $table = 'legacy_circular_misc_import_items';

    protected $fillable = [
        'run_id',
        'source_id',
        'target_module',
        'category_id',
        'source_path',
        'relative_path',
        'file_name',
        'extension',
        'file_size',
        'file_modified_at',
        'sha256',
        'proposed_date',
        'proposed_subject',
        'proposed_number',
        'proposed_original_number',
        'proposed_entity',
        'proposed_direction',
        'proposed_nature',
        'status',
        'duplicate_of_item_id',
        'existing_type',
        'existing_id',
        'notes',
        'circular_id',
        'misc_book_id',
        'imported_at',
        'import_attempts',
        'import_error',
        'copied_file_size',
        'copied_sha256',
        'imported_by',
        'source_verified_at',
        'circular_attachment_id',
        'misc_book_attachment_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'copied_file_size' => 'integer',
            'import_attempts' => 'integer',
            'file_modified_at' => 'datetime',
            'proposed_date' => 'date',
            'imported_at' => 'datetime',
            'source_verified_at' => 'datetime',
        ];
    }

    public function run()
    {
        return $this->belongsTo(
            LegacyCircularMiscImportRun::class,
            'run_id'
        );
    }

    public function source()
    {
        return $this->belongsTo(
            LegacyCircularMiscImportSource::class,
            'source_id'
        );
    }

    public function category()
    {
        return $this->belongsTo(ArchiveCategory::class, 'category_id');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(self::class, 'duplicate_of_item_id');
    }

    public function circular()
    {
        return $this->belongsTo(Circular::class, 'circular_id');
    }

    public function miscBook()
    {
        return $this->belongsTo(MiscBook::class, 'misc_book_id');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            'needs_review' => 'يحتاج مراجعة',
            'existing' => 'موجود في النظام',
            'duplicate' => 'مكرر داخل المصادر',
            'unreadable' => 'غير قابل للقراءة',
            'unsupported' => 'امتداد غير مدعوم',
            'imported' => 'تم الاستيراد',
            'import_failed' => 'فشل الاستيراد',
            default => 'جاهز للمراجعة',
        };
    }

    public function getModuleNameAttribute(): string
    {
        return $this->target_module === 'circular'
            ? 'تعميم'
            : 'كتاب متفرق';
    }

    public function getFileSizeForHumansAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' Bytes';
    }

    public function isImportable(): bool
    {
        return in_array(
            $this->status,
            ['ready', 'needs_review', 'import_failed'],
            true
        );
    }
}
