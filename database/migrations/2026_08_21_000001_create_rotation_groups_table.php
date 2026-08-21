<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotation_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('shift_id');
            $table->date('start_date');
            $table->json('employee_ids');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('shift_id')->references('id')->on('work_shifts')->onDelete('cascade');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('rotation_group_id')->nullable()->after('rotation_start_date');
            $table->foreign('rotation_group_id')->references('id')->on('rotation_groups')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['rotation_group_id']);
            $table->dropColumn('rotation_group_id');
        });
        Schema::dropIfExists('rotation_groups');
    }
};
