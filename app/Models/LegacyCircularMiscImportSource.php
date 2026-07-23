<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyCircularMiscImportSource extends Model
{
    protected $table = 'legacy_circular_misc_import_sources';

    protected $fillable = [
        'run_id', 'source_path', 'target_module', 'category_id',
        'recursive', 'default_direction', 'default_nature',
        'default_entity', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['recursive' => 'boolean', 'sort_order' => 'integer'];
    }

    public function run()
    {
        return $this->belongsTo(LegacyCircularMiscImportRun::class, 'run_id');
    }

    public function category()
    {
        return $this->belongsTo(ArchiveCategory::class, 'category_id');
    }

    public function items()
    {
        return $this->hasMany(LegacyCircularMiscImportItem::class, 'source_id');
    }
}
