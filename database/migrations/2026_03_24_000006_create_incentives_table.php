<?php
// database/migrations/2026_03_24_000006_create_incentives_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // bonus, allowance, commission, other
            $table->decimal('value', 15, 2);
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('incentives');
    }
};