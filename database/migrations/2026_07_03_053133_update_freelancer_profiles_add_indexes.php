<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->unique('user_id');

            $table->index('specialty');
            $table->index('location');
            $table->index('available');
            $table->index('average_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropUnique('freelancer_profiles_user_id_unique');

            $table->dropIndex('freelancer_profiles_specialty_index');
            $table->dropIndex('freelancer_profiles_location_index');
            $table->dropIndex('freelancer_profiles_available_index');
            $table->dropIndex('freelancer_profiles_average_rate_index');
        });
    }
};