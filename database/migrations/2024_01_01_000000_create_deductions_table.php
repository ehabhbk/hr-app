<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deductions')) {
            return;
        }
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('date')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // Add foreign key after table creation (employees may not exist yet at this point)
        if (Schema::hasTable('employees')) {
            Schema::table('deductions', function (Blueprint $table) {
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
