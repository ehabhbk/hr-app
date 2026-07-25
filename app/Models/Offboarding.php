<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Offboarding extends Model {
    protected $fillable = ['employee_id', 'type', 'last_working_date', 'reason', 'checklist', 'settlement_done', 'assets_returned', 'access_revoked', 'exit_interview_done', 'exit_interview_notes', 'status', 'handled_by'];
    protected $casts = ['checklist' => 'array', 'last_working_date' => 'date'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
}
