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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->enum('accion', [
                'LOGIN',
                'LOGOUT',
                'REGISTER',
                'CREATE',
                'UPDATE',
                'DELETE',
                'EXPORT',
                'IMPORT',
                'DOWNLOAD',
                'VIEW'
            ]);
            $table->string('modulo');
            $table->string('entidad');
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->timestamp('creado_en')->useCurrent();

            // Índices para búsquedas frecuentes
            $table->index('usuario_id');
            $table->index('accion');
            $table->index('modulo');
            $table->index('entidad');
            $table->index('creado_en');
            $table->index(['usuario_id', 'creado_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
