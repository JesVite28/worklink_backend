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
        Schema::create('vacancies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('company_profiles')
                ->cascadeOnDelete();

            $table->string('title', 150);
            $table->text('description');
            $table->string('category', 100);
            $table->string('location', 150);
            $table->decimal('salary', 12, 2)->nullable();

            $table->enum('status', [
                'open',
                'paused',
                'closed',
            ])->default('open');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('category');
            $table->index('location');
            $table->index('created_at');

            $table->index(
                ['company_id', 'status'],
                'vacancies_company_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacancies');
    }
};