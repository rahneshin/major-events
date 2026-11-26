<?php

use Database\Seeders\RankedTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 12E — سری‌ها (heats) و نتیجه‌ی سری برای ورزش‌های رتبه‌ای (دوومیدانی/شنا).
 * ستون‌های `result_status/entered_by/confirmed_by` روی heats و `lane/advanced` روی heat_results
 * نسبت به سند اضافه شده‌اند (تأیید دومرحله‌ای نتیجه و علامت صعود).
 * قالب‌های رتبه‌ای در همین migration seed می‌شوند (idempotent؛ بعداً با
 * `php artisan db:seed --class=RankedTemplateSeeder` به‌روز می‌شوند).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('position');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('result_status', 12)->nullable(); // null | entered | confirmed
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['stage_id', 'position']);
        });

        Schema::create('heat_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lane')->nullable();
            $table->decimal('value', 12, 3)->nullable(); // ثانیه، متر، کیلوگرم
            $table->unsignedInteger('rank')->nullable();
            $table->string('status', 4)->default('ok'); // ok | DNS | DNF | DQ
            $table->boolean('advanced')->default(false);

            $table->unique(['heat_id', 'competitor_id']);
        });

        (new RankedTemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_results');
        Schema::dropIfExists('heats');
    }
};
