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
        Schema::create('roles_usuarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->comment('ID del usuario')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('rol_id')
                ->comment('ID del rol')
                ->constrained('roles')
                ->onDelete('cascade');
            $table->timestamps();
            $table->timestamp('asignado_en')->useCurrent();

            // Timestamps de Laravel

            // Índices
            $table->index('usuario_id');
            $table->index('rol_id');

            // Evitar duplicados
            $table->unique(['usuario_id', 'rol_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles_usuarios');
    }
};