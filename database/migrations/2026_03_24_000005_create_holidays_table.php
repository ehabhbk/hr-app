<?php
// database/migrations/2026_03_24_000005_create_holidays_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->integer('duration_days')->default(1);
            $table->json('employee_ids')->nullable(); // optional list of employee IDs
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('holidays');
    }
};