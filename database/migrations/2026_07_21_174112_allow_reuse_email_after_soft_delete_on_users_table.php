<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Elimina el unique original que considera también
         * a los usuarios eliminados lógicamente.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        /*
         * active_email tendrá el correo únicamente cuando
         * deleted_at sea NULL.
         *
         * Cuando la cuenta se elimine, active_email será NULL
         * automáticamente y el correo quedará disponible.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->string('active_email')
                ->nullable()
                ->storedAs(
                    'CASE WHEN deleted_at IS NULL THEN email ELSE NULL END'
                )
                ->after('email');

            $table->unique(
                'active_email',
                'users_active_email_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(
                'users_active_email_unique'
            );

            $table->dropColumn('active_email');

            $table->unique(
                'email',
                'users_email_unique'
            );
        });
    }
};