<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('name');
            $table->string('email')->unique('users_email_unique');
            $table->enum('role', ['administrador', 'supervisor', 'asesor', 'sistemas', 'soporte', 'usuario'])
                ->default('usuario');
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('active')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('supervisor_id', 'users_supervisor_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
