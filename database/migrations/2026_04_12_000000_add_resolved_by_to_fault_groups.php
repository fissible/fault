<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_fault_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolved_at');
            $table->string('resolved_in_version', 50)->nullable()->after('resolved_by');
        });
    }

    public function down(): void
    {
        Schema::table('watch_fault_groups', function (Blueprint $table) {
            $table->dropColumn(['resolved_by', 'resolved_in_version']);
        });
    }
};
