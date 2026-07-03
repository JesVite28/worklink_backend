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
        Schema::table('services', function (Blueprint $table) {
            $table->index('freelancer_id');
            $table->index('category');
            $table->index('location');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('services_freelancer_id_index');
            $table->dropIndex('services_category_index');
            $table->dropIndex('services_location_index');
            $table->dropIndex('services_is_active_index');
            $table->dropIndex('services_created_at_index');
        });
    }
};