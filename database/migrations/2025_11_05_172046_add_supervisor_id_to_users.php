<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','supervisor_id')) {
                $table->unsignedBigInteger('supervisor_id')->nullable()->after('role');
                $table->index('supervisor_id', 'users_supervisor_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','supervisor_id')) {
                $table->dropIndex('users_supervisor_id_idx');
                $table->dropColumn('supervisor_id');
            }
        });
    }
};
