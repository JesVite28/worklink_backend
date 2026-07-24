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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vacancy_id')
                ->constrained('vacancies')
                ->cascadeOnDelete();

            $table->foreignId('freelancer_id')
                ->constrained('freelancer_profiles')
                ->cascadeOnDelete();

            $table->text('message')->nullable();

            $table->enum('status', [
                'pending',
                'accepted',
                'rejected',
            ])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            /*
             * Un freelancer solo puede postularse una vez
             * a la misma vacante, incluso si la postulación
             * fue eliminada lógicamente.
             */
            $table->unique(
                ['vacancy_id', 'freelancer_id'],
                'applications_vacancy_freelancer_unique'
            );

            $table->index('status');
            $table->index('created_at');

            $table->index(
                ['vacancy_id', 'status'],
                'applications_vacancy_status_index'
            );

            $table->index(
                ['freelancer_id', 'status'],
                'applications_freelancer_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};