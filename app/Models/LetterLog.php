<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_id',
        'letter_type',
        'title',
        'reference_number',
        'parameters',
        'content',
        'status',
        'generated_at',
        'printed_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'generated_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public static function generateReferenceNumber($type)
    {
        $prefix = match($type) {
            'termination' => 'TRM',
            'warning' => 'WRN',
            'good_conduct' => 'GC',
            'salary_verification' => 'SV',
            'experience' => 'EX',
            'salary_increase' => 'SI',
            'leave_approval' => 'LA',
            'loan_approval' => 'LN',
            default => 'LTR',
        };

        $year = date('Y');
        $count = self::whereYear('generated_at', $year)->count() + 1;
        
        return sprintf('%s-%s-%04d', $prefix, $year, $count);
    }

    public static function logLetter($type, $title, $employeeId, $parameters = [], $content = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'employee_id' => $employeeId,
            'letter_type' => $type,
            'title' => $title,
            'reference_number' => self::generateReferenceNumber($type),
            'parameters' => $parameters,
            'content' => $content,
            'status' => 'draft',
            'generated_at' => now(),
        ]);
    }

    public function markAsPrinted()
    {
        $this->update([
            'status' => 'printed',
            'printed_at' => now(),
        ]);
    }
}
