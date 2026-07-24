<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('type', 80);
            $table->text('message');
            $table->boolean('is_read')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_read'], 'notifications_user_read_index');
            $table->index(['user_id', 'type'], 'notifications_user_type_index');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};