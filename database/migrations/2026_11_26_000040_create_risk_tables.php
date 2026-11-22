<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 10 — ریسک و حادثه.
 *
 * - score = likelihood × impact؛ در مدل هنگام ذخیره محاسبه می‌شود (ستون برای مرتب‌سازی/فیلتر).
 * - رابطه‌ی ریسک ← وظیفه‌ی کنترلی با جدول واسط `risk_task` (وظیفه‌ی حذف‌شده با cascade جدا می‌شود).
 * - شماره‌ی migration عمداً ۴۰ است تا با migrationهای منابع انسانی (۳۰ و ۳۱) هم‌پیشوند نشود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('category', 24);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('likelihood');          // 1–5
            $table->unsignedTinyInteger('impact');              // 1–5
            $table->unsignedTinyInteger('score');               // likelihood × impact
            $table->string('response', 16)->default('reduce');  // accept|reduce|avoid|transfer
            $table->text('control_actions')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('open');     // open|mitigating|closed|occurred
            $table->date('review_date')->nullable();
            $table->string('template_key', 40)->nullable();     // مبدأ ریسک‌های ساخته‌شده از چک‌لیست پیشنهادی
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'score']);
            $table->index(['event_id', 'template_key']);
        });

        Schema::create('risk_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['risk_id', 'task_id']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('risk_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('severity', 16);                     // low|medium|high|critical
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->text('resolution')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('risk_task');
        Schema::dropIfExists('risks');
    }
};
