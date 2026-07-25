<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Training extends Model {
    protected $fillable = ['employee_id', 'course_name', 'institution', 'start_date', 'end_date', 'certificate_expiry', 'certificate_file', 'notes', 'status'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'certificate_expiry' => 'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
