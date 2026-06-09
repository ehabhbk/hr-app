<?php
// database/migrations/2026_03_24_000004_create_shift_assignments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->timestamps();
            $table->unique(['employee_id','date']);
        });
    }
    public function down() {
        Schema::dropIfExists('shift_assignments');
    }
};