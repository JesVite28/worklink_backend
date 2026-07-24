<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('receiver_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('content');
            $table->boolean('is_read')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sender_id', 'receiver_id', 'created_at'], 'messages_sender_receiver_date_index');
            $table->index(['receiver_id', 'is_read'], 'messages_receiver_read_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};