<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'report_type',
        'title',
        'parameters',
        'filters',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'filters' => 'array',
        'generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function logReport($type, $title, $parameters = [], $filters = [])
    {
        return self::create([
            'user_id' => auth()->id(),
            'report_type' => $type,
            'title' => $title,
            'parameters' => $parameters,
            'filters' => $filters,
            'status' => 'generated',
            'generated_at' => now(),
        ]);
    }
}
