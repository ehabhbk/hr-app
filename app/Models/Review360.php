<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review360 extends Model
{
    use HasFactory;

    protected $table = 'reviews_360';

    protected $fillable = [
        'employee_id', 'reviewer_id', 'reviewer_type',
        'communication_score', 'teamwork_score', 'leadership_score',
        'technical_score', 'problem_solving_score',
        'strengths', 'improvements', 'comments', 'review_period',
    ];

    protected $casts = [
        'communication_score' => 'decimal:1', 'teamwork_score' => 'decimal:1',
        'leadership_score' => 'decimal:1', 'technical_score' => 'decimal:1',
        'problem_solving_score' => 'decimal:1', 'review_period' => 'date',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
}
