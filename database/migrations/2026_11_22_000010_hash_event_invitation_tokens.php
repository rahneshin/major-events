<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توکن دعوت رویداد از متن خام به هش SHA-256 تبدیل می‌شود (هم‌راستا با organization_invitations).
 * توکن‌های خام قبلی هیچ‌جا ارسال نشده بودند؛ مقدار موجود در همان ستون هش می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_invitations', function (Blueprint $table) {
            $table->dropUnique(['token']);
        });

        Schema::table('event_invitations', function (Blueprint $table) {
            $table->renameColumn('token', 'token_hash');
        });

        DB::table('event_invitations')->orderBy('id')->each(function ($row) {
            DB::table('event_invitations')
                ->where('id', $row->id)
                ->update(['token_hash' => hash('sha256', (string) $row->token_hash)]);
        });

        Schema::table('event_invitations', function (Blueprint $table) {
            $table->unique('token_hash');
        });
    }

    public function down(): void
    {
        // هش برگشت‌پذیر نیست؛ فقط نام ستون برمی‌گردد (دعوت‌های pending عملاً باطل می‌شوند).
        Schema::table('event_invitations', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
        });

        Schema::table('event_invitations', function (Blueprint $table) {
            $table->renameColumn('token_hash', 'token');
        });

        Schema::table('event_invitations', function (Blueprint $table) {
            $table->unique('token');
        });
    }
};
