<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $invalidRolesCount = DB::table('users')
            ->where(function ($query) {
                $query->whereNull('role')
                    ->orWhereNotIn('role', ['administrador', 'supervisor', 'asesor', 'soporte']);
            })
            ->count();

        if ($invalidRolesCount > 0) {
            throw new \RuntimeException("No se puede alterar el ENUM: se encontraron {$invalidRolesCount} registros con roles no autorizados.");
        }

        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('administrador','supervisor','asesor','soporte') NOT NULL");
    }

    public function down(): void
    {
        // Solo se amplía el dominio del ENUM. No es posible reconstruir qué roles
        // históricos tenía cada usuario sin una fuente de datos externa.
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('administrador','supervisor','asesor','sistemas','soporte','usuario') NOT NULL");
    }
};
