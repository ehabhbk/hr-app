<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AttendanceExcuse extends Model {
    protected $fillable = ['employee_id', 'date', 'type', 'reason', 'attachment', 'status', 'admin_note', 'reviewed_by'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
