<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'created_by',
        'title',
        'body',
        'priority',
        'target',
        'target_ids',
        'is_active',
        'publish_at',
        'expire_at',
    ];

    protected $casts = [
        'target_ids' => 'array',
        'is_active' => 'boolean',
        'publish_at' => 'datetime',
        'expire_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
