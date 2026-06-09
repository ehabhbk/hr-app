<?php
// database/migrations/2026_03_24_000008_create_advances_requests_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('advances_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending');
            $table->string('type')->default('short');
            $table->integer('installments')->default(1);
            $table->date('date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('advances_requests');
    }
};