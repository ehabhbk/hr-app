<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GpsCheckin extends Model {
    protected $fillable = ['employee_id', 'type', 'latitude', 'longitude', 'address', 'timestamp', 'notes'];
    protected $casts = ['latitude' => 'decimal:8', 'longitude' => 'decimal:8', 'timestamp' => 'datetime'];
    public function employee() { return $this->belongsTo(Employee::class); }
}
