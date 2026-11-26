<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->boolean('in_app')->default(true);
            $table->boolean('sms')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });

        Schema::create('event_announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('urgent')->default(false);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_announcements');
        Schema::dropIfExists('notification_preferences');
    }
};
