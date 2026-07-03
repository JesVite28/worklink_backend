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
        Schema::table('contract_requests', function (Blueprint $table) {
            $table->decimal('budget', 10, 2)->nullable()->change();

            $table->index('client_id');
            $table->index('freelancer_id');
            $table->index('service_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_requests', function (Blueprint $table) {
            $table->dropIndex('contract_requests_client_id_index');
            $table->dropIndex('contract_requests_freelancer_id_index');
            $table->dropIndex('contract_requests_service_id_index');
            $table->dropIndex('contract_requests_status_index');
            $table->dropIndex('contract_requests_created_at_index');

            $table->decimal('budget', 10, 2)->nullable(false)->change();
        });
    }
};