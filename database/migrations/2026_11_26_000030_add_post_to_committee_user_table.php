<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_user', function (Blueprint $table) {
            // «پست» فرد در کمیته (متن آزاد، مثلاً «مسئول پذیرایی»)؛ جدا از نقش head|member
            $table->string('post', 120)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('committee_user', function (Blueprint $table) {
            $table->dropColumn('post');
        });
    }
};
