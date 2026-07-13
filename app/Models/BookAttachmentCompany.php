<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookAttachmentCompany extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'attachment_company_name', 'name');
    }
}
