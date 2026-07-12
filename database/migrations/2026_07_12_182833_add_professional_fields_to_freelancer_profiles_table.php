<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * La tarifa deja de ser exclusivamente por hora.
         */
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->renameColumn('hourly_rate', 'rate');
        });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->string('service_area', 150)
                ->nullable()
                ->after('location');

            $table->string('work_mode', 30)
                ->nullable()
                ->after('service_area');

            $table->text('experience')
                ->nullable()
                ->after('work_mode');

            $table->string('rate_type', 30)
                ->nullable()
                ->after('experience');

            $table->json('languages')
                ->nullable()
                ->after('rate');

            $table->string('website')
                ->nullable()
                ->after('languages');

            $table->string('facebook')
                ->nullable()
                ->after('website');

            $table->string('instagram')
                ->nullable()
                ->after('facebook');

            $table->string('linkedin')
                ->nullable()
                ->after('instagram');

            $table->string('github')
                ->nullable()
                ->after('linkedin');

            $table->string('portfolio_url')
                ->nullable()
                ->after('github');
        });

        /*
         * Las tarifas anteriores eran por hora.
         */
        DB::table('freelancer_profiles')
            ->whereNotNull('rate')
            ->update([
                'rate_type' => 'hourly',
            ]);
    }

    public function down(): void
    {
        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'service_area',
                'work_mode',
                'experience',
                'rate_type',
                'languages',
                'website',
                'facebook',
                'instagram',
                'linkedin',
                'github',
                'portfolio_url',
            ]);
        });

        Schema::table('freelancer_profiles', function (Blueprint $table) {
            $table->renameColumn('rate', 'hourly_rate');
        });
    }
};