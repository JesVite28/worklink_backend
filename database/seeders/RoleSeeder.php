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
        // Crear roles iniciales del sistema
        $rolesData = [
            ['nombre' => 'user', 'descripcion' => 'Usuario estándar del sistema'],
            ['nombre' => 'admin', 'descripcion' => 'Administrador con acceso total'],
            ['nombre' => 'empresa', 'descripcion' => 'Cuenta empresarial'],
            ['nombre' => 'freelancer', 'descripcion' => 'Cuenta freelancer'],
        ];

        foreach ($rolesData as $roleData) {
            Role::firstOrCreate(
                ['nombre' => $roleData['nombre']],
                ['descripcion' => $roleData['descripcion']]
            );
        }
    }
}
