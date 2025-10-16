<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'fernando.quinones@kpinvest.pe'],
            [
                'name' => 'Admin',
                'password' => Hash::make('FQ!2025@KpiInvest#87'),
                'role' => 'administrador',
            ]
        );
    }
}
