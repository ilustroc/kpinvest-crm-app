<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ccd_association_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('execution_id')->unique('ccd_association_runs_execution_unique');
            $table->string('mode', 20);
            $table->string('status', 30)->default('running');
            $table->json('filters')->nullable();
            $table->unsignedInteger('batch_size');
            $table->unsignedInteger('last_ccd_id')->default(0);
            $table->unsignedBigInteger('processed')->default(0);
            $table->unsignedBigInteger('eligible')->default(0);
            $table->unsignedBigInteger('confirmed')->default(0);
            $table->unsignedBigInteger('already_confirmed')->default(0);
            $table->unsignedBigInteger('not_eligible')->default(0);
            $table->unsignedBigInteger('reviews')->default(0);
            $table->unsignedBigInteger('conflicts')->default(0);
            $table->unsignedBigInteger('errors')->default(0);
            $table->decimal('elapsed_seconds', 14, 3)->default(0);
            $table->decimal('documents_per_second', 12, 4)->default(0);
            $table->unsignedBigInteger('last_query_count')->default(0);
            $table->unsignedBigInteger('peak_memory_bytes')->default(0);
            $table->string('last_report_path', 500)->nullable();
            $table->string('stop_reason', 150)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_heartbeat_at'], 'ccd_association_runs_status_heartbeat_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ccd_association_runs');
    }
};
