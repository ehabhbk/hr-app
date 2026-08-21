<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('okr_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('employee'); // employee, department
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 10, 2)->default(0);
            $table->decimal('current_value', 10, 2)->default(0);
            $table->enum('status', ['on_track', 'at_risk', 'completed', 'missed'])->default('on_track');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('okr_goals');
    }
};
