<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['sistemas', 'usuario'])
            ->update(['role' => 'soporte']);

        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('administrador','supervisor','asesor','soporte') NOT NULL DEFAULT 'soporte'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('administrador','supervisor','asesor','sistemas','soporte','usuario') NOT NULL DEFAULT 'usuario'");
    }
};
