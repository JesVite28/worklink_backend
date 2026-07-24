<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('reported_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('reason', 150);
            $table->text('description');

            $table->enum('status', [
                'pending',
                'reviewed',
                'resolved',
            ])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['reporter_id', 'created_at'],
                'reports_reporter_date_index'
            );

            $table->index(
                ['reported_id', 'status'],
                'reports_reported_status_index'
            );

            $table->index(
                ['status', 'created_at'],
                'reports_status_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};