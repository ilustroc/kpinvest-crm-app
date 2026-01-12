<?php
// database/migrations/xxxx_xx_xx_create_cliente_bloqueos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('cliente_bloqueos', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('user_id');
      $table->string('dni', 20);
      $table->string('motivo', 255)->nullable();
      $table->timestamps();

      $table->unique(['user_id','dni']);
      $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
      $table->index('dni');
    });
  }
  public function down(): void {
    Schema::dropIfExists('cliente_bloqueos');
  }
};
