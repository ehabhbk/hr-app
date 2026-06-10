<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('appearance')->default(0);
            $table->unsignedTinyInteger('behavior')->default(0);
            $table->unsignedTinyInteger('performance')->default(0);
            $table->unsignedTinyInteger('total_score')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_evaluations');
    }
};
