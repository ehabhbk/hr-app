<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'title', 'type', 'file_path',
        'issue_date', 'expiry_date', 'notes',
    ];

    protected $casts = ['issue_date' => 'date', 'expiry_date' => 'date'];

    public function employee() { return $this->belongsTo(Employee::class); }
}
