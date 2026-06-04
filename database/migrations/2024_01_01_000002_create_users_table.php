<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->enum('tipo_cuenta', ['Cliente', 'Freelancer', 'Empresa']);
            $table->string('telefono')->nullable();
            $table->string('foto_perfil')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes('deleted_at');

            $table->index('email');
            $table->index('tipo_cuenta');
            $table->index('activo');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
