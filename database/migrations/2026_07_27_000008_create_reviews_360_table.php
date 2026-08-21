<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews_360', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->enum('reviewer_type', ['manager', 'peer', 'subordinate', 'self']);
            $table->decimal('communication_score', 3, 1)->nullable();
            $table->decimal('teamwork_score', 3, 1)->nullable();
            $table->decimal('leadership_score', 3, 1)->nullable();
            $table->decimal('technical_score', 3, 1)->nullable();
            $table->decimal('problem_solving_score', 3, 1)->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('comments')->nullable();
            $table->date('review_period');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews_360');
    }
};
