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
        Schema::table('briefcases', function (Blueprint $table) {
            $table->renameColumn('url_image', 'image_url');
            $table->renameColumn('url_proyecto', 'project_url');

            $table->index('freelancer_id');
            $table->index('title');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('briefcases', function (Blueprint $table) {
            $table->dropIndex('briefcases_freelancer_id_index');
            $table->dropIndex('briefcases_title_index');
            $table->dropIndex('briefcases_created_at_index');

            $table->renameColumn('image_url', 'url_image');
            $table->renameColumn('project_url', 'url_proyecto');
        });
    }
};