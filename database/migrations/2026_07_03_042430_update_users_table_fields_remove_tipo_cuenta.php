<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Quitar índices de campos que se van a renombrar o eliminar
            $table->dropIndex('users_tipo_cuenta_index');
            $table->dropIndex('users_activo_index');

            // Este índice está duplicado porque email ya tiene unique()
            $table->dropIndex('users_email_index');

            // Renombrar campos a convención en inglés
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('apellido', 'last_name');
            $table->renameColumn('password_hash', 'password');
            $table->renameColumn('telefono', 'phone');
            $table->renameColumn('foto_perfil', 'profile_photo');
            $table->renameColumn('activo', 'is_active');

            // Eliminar tipo_cuenta porque ahora el rol define la vista/permisos
            $table->dropColumn('tipo_cuenta');

            // Nuevo índice con nombre correcto
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_active_index');

            $table->renameColumn('name', 'nombre');
            $table->renameColumn('last_name', 'apellido');
            $table->renameColumn('password', 'password_hash');
            $table->renameColumn('phone', 'telefono');
            $table->renameColumn('profile_photo', 'foto_perfil');
            $table->renameColumn('is_active', 'activo');

            $table->enum('tipo_cuenta', ['Cliente', 'Freelancer', 'Empresa'])
                ->default('Cliente')
                ->after('password_hash');

            $table->index('email');
            $table->index('tipo_cuenta');
            $table->index('activo');
        });
    }
};