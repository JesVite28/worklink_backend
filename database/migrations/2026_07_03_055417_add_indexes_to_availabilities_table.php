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
        Schema::table('availabilities', function (Blueprint $table) {
            $table->index('freelancer_id');
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index(['freelancer_id', 'start_date', 'end_date'], 'availabilities_freelancer_dates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('availabilities', function (Blueprint $table) {
            $table->dropIndex('availabilities_freelancer_id_index');
            $table->dropIndex('availabilities_status_index');
            $table->dropIndex('availabilities_start_date_index');
            $table->dropIndex('availabilities_end_date_index');
            $table->dropIndex('availabilities_freelancer_dates_index');
        });
    }
};