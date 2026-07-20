<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Se agrega un índice normal para mantener optimizada
         * la llave foránea de user_id.
         */
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->index(
                'user_id',
                'freelancer_profiles_user_id_index'
            );
        });

        /*
         * Se elimina el índice UNIQUE porque impedía crear
         * otro perfil después de aplicar Soft Deletes.
         */
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropUnique(
                'freelancer_profiles_user_id_unique'
            );
        });
    }

    public function down(): void
    {
        /*
         * Primero se restaura el índice único.
         */
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->unique(
                'user_id',
                'freelancer_profiles_user_id_unique'
            );
        });

        /*
         * Después se elimina el índice normal.
         */
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropIndex(
                'freelancer_profiles_user_id_index'
            );
        });
    }
};