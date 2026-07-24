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
        Schema::create('company_profiles', function (Blueprint $table) {
            /*
             * InnoDB es necesario para utilizar llaves foráneas en MySQL.
             */
            $table->engine = 'InnoDB';

            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('company_name', 150);
            $table->text('description')->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('location', 150)->nullable();
            $table->decimal('average_rate', 3, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * Cada usuario puede tener un único perfil empresarial.
             * Si se elimina con SoftDeletes, se restaura el mismo perfil.
             */
            $table->unique(
                'user_id',
                'company_profiles_user_id_unique'
            );

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index('company_name');
            $table->index('industry');
            $table->index('location');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};