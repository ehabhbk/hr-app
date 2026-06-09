<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvanceRequest extends Model
{
    use HasFactory;

    protected $table = 'advances_requests';

    protected $fillable = ['employee_id', 'amount', 'status', 'type', 'installments', 'date', 'note', 'remaining_amount', 'paid_amount', 'monthly_installment'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
