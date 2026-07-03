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
        $this->createUserWithRole(
            [
                'name' => 'Administrador',
                'last_name' => 'Sistema',
                'email' => 'admin@worklink.com',
                'password' => Hash::make('admin123'),
                'phone' => '+1234567890',
                'is_active' => true,
            ],
            'admin'
        );

        $this->createUserWithRole(
            [
                'name' => 'Juan',
                'last_name' => 'Cliente',
                'email' => 'cliente@worklink.com',
                'password' => Hash::make('cliente123'),
                'phone' => '+0987654321',
                'is_active' => true,
            ],
            'cliente'
        );

        $this->createUserWithRole(
            [
                'name' => 'María',
                'last_name' => 'Freelancer',
                'email' => 'freelancer@worklink.com',
                'password' => Hash::make('freelancer123'),
                'phone' => '+1122334455',
                'is_active' => true,
            ],
            'freelancer'
        );

        $this->createUserWithRole(
            [
                'name' => 'TechCorp',
                'last_name' => 'Solutions',
                'email' => 'empresa@worklink.com',
                'password' => Hash::make('empresa123'),
                'phone' => '+5556667777',
                'is_active' => true,
            ],
            'empresa'
        );
    }

    /**
     * Create or update a user and assign only one role.
     */
    private function createUserWithRole(array $userData, string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();

        if (! $role) {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => $userData['email']],
            $userData
        );

        $user->roles()->sync([
            $role->id => [
                'assigned_at' => now(),
            ],
        ]);
    }
}