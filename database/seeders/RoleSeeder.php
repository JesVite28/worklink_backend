<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
         * Si anteriormente existía el rol "user",
         * lo eliminamos para evitar que se siga asignando.
         */
        Role::where('name', 'user')->delete();

        $rolesData = [
            [
                'name' => 'cliente',
                'description' => 'Cuenta cliente para solicitar servicios y contratar freelancers',
            ],
            [
                'name' => 'freelancer',
                'description' => 'Cuenta freelancer para publicar servicios y postularse a vacantes',
            ],
            [
                'name' => 'empresa',
                'description' => 'Cuenta empresarial para publicar vacantes y contratar talento',
            ],
            [
                'name' => 'admin',
                'description' => 'Administrador con acceso total al sistema',
            ],
        ];

        foreach ($rolesData as $roleData) {
            Role::updateOrCreate(
                ['name' => $roleData['name']],
                ['description' => $roleData['description']]
            );
        }
    }
}