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
        Schema::table('contracts', function (Blueprint $table) {
            $table->unique('request_id');

            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique('contracts_request_id_unique');

            $table->dropIndex('contracts_status_index');
            $table->dropIndex('contracts_start_date_index');
            $table->dropIndex('contracts_end_date_index');
            $table->dropIndex('contracts_created_at_index');
        });
    }
};