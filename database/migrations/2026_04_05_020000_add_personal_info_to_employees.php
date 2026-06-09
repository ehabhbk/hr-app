<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable()->after('phone');
            $table->date('birth_date')->nullable()->after('gender');
            $table->string('id_number', 50)->nullable()->after('birth_date');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable()->after('id_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['gender', 'birth_date', 'id_number', 'marital_status']);
        });
    }
};
