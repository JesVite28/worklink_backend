<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener roles
        $adminRole = Role::where('nombre', 'admin')->first();
        $userRole = Role::where('nombre', 'user')->first();
        $empresaRole = Role::where('nombre', 'empresa')->first();
        $freelancerRole = Role::where('nombre', 'freelancer')->first();

        // Usuario Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@worklink.com'],
            [
                'nombre' => 'Administrador',
                'apellido' => 'Sistema',
                'password_hash' => Hash::make('admin123'),
                'tipo_cuenta' => 'Empresa',
                'telefono' => '+1234567890',
                'activo' => true,
            ]
        );

        // Asignar roles (admin + empresa)
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching($adminRole->id);
        }
        if ($empresaRole) {
            $admin->roles()->syncWithoutDetaching($empresaRole->id);
        }

        // Usuario Cliente
        $cliente = User::firstOrCreate(
            ['email' => 'cliente@worklink.com'],
            [
                'nombre' => 'Juan',
                'apellido' => 'Cliente',
                'password_hash' => Hash::make('cliente123'),
                'tipo_cuenta' => 'Cliente',
                'telefono' => '+0987654321',
                'activo' => true,
            ]
        );

        // Asignar rol user
        if ($userRole) {
            $cliente->roles()->syncWithoutDetaching($userRole->id);
        }

        // Usuario Freelancer
        $freelancer = User::firstOrCreate(
            ['email' => 'freelancer@worklink.com'],
            [
                'nombre' => 'María',
                'apellido' => 'Freelancer',
                'password_hash' => Hash::make('freelancer123'),
                'tipo_cuenta' => 'Freelancer',
                'telefono' => '+1122334455',
                'activo' => true,
            ]
        );

        // Asignar roles (freelancer + user)
        if ($userRole) {
            $freelancer->roles()->syncWithoutDetaching($userRole->id);
        }
        if ($freelancerRole) {
            $freelancer->roles()->syncWithoutDetaching($freelancerRole->id);
        }

        // Usuario Empresa
        $empresa = User::firstOrCreate(
            ['email' => 'empresa@worklink.com'],
            [
                'nombre' => 'TechCorp',
                'apellido' => 'Solutions',
                'password_hash' => Hash::make('empresa123'),
                'tipo_cuenta' => 'Empresa',
                'telefono' => '+5556667777',
                'activo' => true,
            ]
        );

        // Asignar roles (empresa + user)
        if ($userRole) {
            $empresa->roles()->syncWithoutDetaching($userRole->id);
        }
        if ($empresaRole) {
            $empresa->roles()->syncWithoutDetaching($empresaRole->id);
        }
    }
}
