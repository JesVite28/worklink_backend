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
        Schema::create('briefcases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('freelancer_id')->constrained('freelancer_profiles')->onDelete('cascade');
            $table->string('title',150);
            $table->text('description')->nullable();
            $table->string('url_image')->nullable();
            $table->string('url_proyecto')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('briefcases');
    }
};
