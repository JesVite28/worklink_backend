<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'two_factor_challenges',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('purpose', 20)
                    ->default('login');

                /*
                 * Se guarda el hash del token de desafío,
                 * nunca el token original.
                 */
                $table->char('token_hash', 64)
                    ->unique();

                /*
                 * El código de seis dígitos también se
                 * guardará cifrado mediante Hash::make().
                 */
                $table->string('code_hash');

                $table->unsignedTinyInteger('attempts')
                    ->default(0);

                $table->timestamp('expires_at')
                    ->index();

                $table->timestamp('verified_at')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'user_id',
                        'purpose',
                        'expires_at',
                    ],
                    'two_factor_challenges_lookup_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'two_factor_challenges'
        );
    }
};