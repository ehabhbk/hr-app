<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $existing = Schema::getColumnListing('employees');
            if (!in_array('gender', $existing)) {
                $table->enum('gender', ['male', 'female'])->nullable()->after('phone');
            }
            if (!in_array('birth_date', $existing)) {
                $afterBirth = in_array('gender', $existing) ? 'gender' : 'phone';
                $table->date('birth_date')->nullable()->after($afterBirth);
            }
            if (!in_array('id_number', $existing)) {
                $afterId = in_array('birth_date', $existing) ? 'birth_date' : (in_array('gender', $existing) ? 'gender' : 'phone');
                $table->string('id_number', 50)->nullable()->after($afterId);
            }
            if (!in_array('marital_status', $existing)) {
                $afterMs = in_array('id_number', $existing) ? 'id_number' : (in_array('birth_date', $existing) ? 'birth_date' : 'phone');
                $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable()->after($afterMs);
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['gender', 'birth_date', 'id_number', 'marital_status']);
        });
    }
};
