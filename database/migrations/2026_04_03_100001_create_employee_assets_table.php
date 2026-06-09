<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['fixed', 'movable'])->default('fixed');
            $table->decimal('value', 12, 2)->default(0);
            $table->enum('status', ['active', 'returned', 'damaged', 'lost'])->default('active');
            $table->date('issue_date')->nullable();
            $table->date('return_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assets');
    }
};
