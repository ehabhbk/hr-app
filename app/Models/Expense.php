<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Expense extends Model {
    protected $fillable = ['employee_id', 'category', 'amount', 'expense_date', 'description', 'receipt_file', 'status', 'admin_note', 'reviewed_by', 'paid'];
    protected $casts = ['amount' => 'decimal:2', 'expense_date' => 'date', 'paid' => 'boolean'];
    public function employee() { return $this->belongsTo(Employee::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
