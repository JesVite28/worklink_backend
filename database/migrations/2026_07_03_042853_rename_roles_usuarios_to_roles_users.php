<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles_users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('role_id')
                ->constrained('roles')
                ->onDelete('cascade');

            $table->timestamp('assigned_at')->useCurrent();

            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
        });

        DB::table('roles_usuarios')
            ->orderBy('id')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('roles_users')->insert([
                        'id' => $record->id,
                        'user_id' => $record->usuario_id,
                        'role_id' => $record->rol_id,
                        'assigned_at' => $record->asignado_en,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                    ]);
                }
            });

        Schema::dropIfExists('roles_usuarios');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('roles_usuarios', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('rol_id')
                ->constrained('roles')
                ->onDelete('cascade');

            $table->timestamps();

            $table->timestamp('asignado_en')->useCurrent();

            $table->unique(['usuario_id', 'rol_id']);
            $table->index('usuario_id');
            $table->index('rol_id');
        });

        DB::table('roles_users')
            ->orderBy('id')
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    DB::table('roles_usuarios')->insert([
                        'id' => $record->id,
                        'usuario_id' => $record->user_id,
                        'rol_id' => $record->role_id,
                        'asignado_en' => $record->assigned_at,
                        'created_at' => $record->created_at,
                        'updated_at' => $record->updated_at,
                    ]);
                }
            });

        Schema::dropIfExists('roles_users');
    }
};