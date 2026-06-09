<?php
// database/migrations/2026_03_24_000007_create_leaves_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type'); // annual, sick, maternity, etc.
            $table->date('from_date');
            $table->date('to_date');
            $table->integer('days')->default(0);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('leaves');
    }
};