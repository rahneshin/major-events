<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Committees are soft-deleted (project rule). A DB-level unique(event_id, name)
 * would block re-creating a committee whose name belonged to a deleted one,
 * so uniqueness is enforced in CommitteeService (ignoring trashed rows) instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'name']);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique(['event_id', 'name']);
        });
    }
};
