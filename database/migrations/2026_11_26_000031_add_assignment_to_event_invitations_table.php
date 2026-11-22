<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_invitations', function (Blueprint $table) {
            // کمیته/پستی که با پذیرش دعوت به فرد داده می‌شود
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('post', 120)->nullable();
            $table->boolean('is_head')->default(false);
            // نامی که دعوت‌کننده وارد کرده؛ اگر کاربر نام نداشت با پذیرش پر می‌شود
            $table->string('invitee_name', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('committee_id');
            $table->dropColumn(['post', 'is_head', 'invitee_name']);
        });
    }
};
