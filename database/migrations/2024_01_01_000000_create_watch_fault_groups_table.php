<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_fault_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_hash', 64)->unique()->comment('SHA-256 of class|relative_file|line');
            $table->string('class_name');
            $table->text('message')->nullable();
            $table->string('file')->nullable()->comment('Relative path from app base');
            $table->unsignedInteger('line')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status', 20)->default('open')->comment('open|resolved|ignored');
            $table->text('resolution_notes')->nullable()->comment('Developer comments or AI evaluation');
            $table->timestamp('resolved_at')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->json('sample_context')->nullable()->comment('Stack trace frames at time of first capture');
            $table->text('generated_test')->nullable()->comment('PHPUnit test skeleton');
            $table->timestamps();

            $table->index('status');
            $table->index('last_seen_at');
            $table->index('class_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_fault_groups');
    }
};
