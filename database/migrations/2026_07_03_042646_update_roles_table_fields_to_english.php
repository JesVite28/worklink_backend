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
        Schema::table('roles', function (Blueprint $table) {
            // Quitar índices antiguos antes de renombrar columnas
            $table->dropIndex('roles_nombre_index');
            $table->dropUnique('roles_nombre_unique');

            // Renombrar campos a inglés
            $table->renameColumn('nombre', 'name');
            $table->renameColumn('descripcion', 'description');

            // Eliminar campo duplicado, porque created_at ya cumple esa función
            $table->dropColumn('creado_en');
        });

        Schema::table('roles', function (Blueprint $table) {
            // Nuevo índice único con el nombre correcto
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_unique');

            $table->renameColumn('name', 'nombre');
            $table->renameColumn('description', 'descripcion');

            $table->timestamp('creado_en')->useCurrent()->after('descripcion');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique('nombre');
            $table->index('nombre');
        });
    }
};