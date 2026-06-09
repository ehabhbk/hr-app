<?php
// database/migrations/2026_03_24_000009_create_warnings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('warnings');
    }
};