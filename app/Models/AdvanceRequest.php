<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvanceRequest extends Model
{
    use HasFactory;

    protected $table = 'advances_requests';

    protected $fillable = ['employee_id', 'amount', 'status', 'type', 'installments', 'date', 'note', 'attachment', 'remaining_amount', 'paid_amount', 'monthly_installment', 'installments_detail'];

    protected $casts = [
        'installments_detail' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getPaidInstallmentsCountAttribute(): int
    {
        $detail = $this->installments_detail ?? [];
        return count(array_filter($detail, fn($i) => ($i['paid'] ?? false)));
    }

    public function getTotalPaidAmountAttribute(): float
    {
        $detail = $this->installments_detail ?? [];
        return (float) array_sum(array_column(array_filter($detail, fn($i) => ($i['paid'] ?? false)), 'amount'));
    }

    public function getTotalRemainingAmountAttribute(): float
    {
        return (float) ($this->amount ?? 0) - $this->total_paid_amount;
    }

    public function syncFromDetail(): void
    {
        $detail = $this->installments_detail ?? [];
        $this->installments = count($detail);
        $this->remaining_amount = $this->total_remaining_amount;
        $this->monthly_installment = $this->installments > 0 ? $this->amount / $this->installments : 0;
    }
}
