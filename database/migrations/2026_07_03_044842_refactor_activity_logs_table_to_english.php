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
        Schema::rename('activity_logs', 'activity_logs_old');

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('action', [
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

            $table->string('module', 80);
            $table->string('entity', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('action');
            $table->index('module');
            $table->index('entity');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });

        DB::table('activity_logs_old')
            ->orderBy('id')
            ->chunkById(100, function ($logs) {
                foreach ($logs as $log) {
                    DB::table('activity_logs')->insert([
                        'id' => $log->id,
                        'user_id' => $log->usuario_id,
                        'action' => $log->accion,
                        'module' => $log->modulo,
                        'entity' => $log->entidad,
                        'entity_id' => $log->entidad_id,
                        'description' => $log->descripcion,
                        'ip_address' => $log->ip_address,
                        'user_agent' => $log->user_agent,
                        'created_at' => $log->creado_en ?? now(),
                        'updated_at' => $log->creado_en ?? now(),
                    ]);
                }
            });

        Schema::dropIfExists('activity_logs_old');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('activity_logs', 'activity_logs_new');

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

            $table->index('usuario_id');
            $table->index('accion');
            $table->index('modulo');
            $table->index('entidad');
            $table->index('creado_en');
            $table->index(['usuario_id', 'creado_en']);
        });

        DB::table('activity_logs_new')
            ->orderBy('id')
            ->chunkById(100, function ($logs) {
                foreach ($logs as $log) {
                    if ($log->user_id === null) {
                        continue;
                    }

                    DB::table('activity_logs')->insert([
                        'id' => $log->id,
                        'usuario_id' => $log->user_id,
                        'accion' => $log->action,
                        'modulo' => $log->module,
                        'entidad' => $log->entity,
                        'entidad_id' => $log->entity_id,
                        'descripcion' => $log->description,
                        'ip_address' => $log->ip_address ?? '0.0.0.0',
                        'user_agent' => $log->user_agent,
                        'creado_en' => $log->created_at ?? now(),
                    ]);
                }
            });

        Schema::dropIfExists('activity_logs_new');
    }
};