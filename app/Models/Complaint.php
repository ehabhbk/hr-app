<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'type', 'subject', 'description', 'status',
        'response', 'assigned_to', 'attachment',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
}
