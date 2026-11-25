<?php

use Database\Seeders\CompetitionTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 12A — ورزش‌ها (competitions)، مسئول/ثبت‌کننده، قالب‌ها، شرکت‌کننده‌ها و ترکیب تیم.
 * ستون JSON = json (قابل‌حمل SQLite/PostgreSQL). قالب‌های آماده در همین migration seed می‌شوند
 * (idempotent؛ بعداً با `php artisan db:seed --class=CompetitionTemplateSeeder` به‌روز می‌شوند).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('competitions')->nullOnDelete();
            $table->string('name', 120);
            $table->string('sport_code', 40)->default('generic');
            $table->string('gender', 12)->default('open');
            $table->string('participant_type', 12);
            $table->string('result_type', 12)->default('match');
            $table->json('scoring_config');
            $table->json('settings')->nullable();
            $table->string('status', 16)->default('registration');
            $table->boolean('requires_confirmation')->default(true);
            $table->unsignedInteger('capacity')->nullable();
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->string('public_token', 64)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
        });

        Schema::create('competition_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // manager | scorer
            $table->timestamps();

            $table->unique(['competition_id', 'user_id']);
        });

        Schema::create('competition_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_fa');
            $table->string('participant_type', 12);
            $table->string('result_type', 12)->default('match');
            $table->json('scoring_config');
            $table->json('stages');
            $table->timestamps();
        });

        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('affiliation', 120)->nullable();
            $table->unsignedInteger('seed')->nullable();
            $table->string('status', 12)->default('registered');
            $table->json('documents')->nullable();
            $table->unsignedInteger('final_rank')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'status']);
        });

        Schema::create('competitor_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name', 120);
            $table->string('role', 40)->nullable();
            $table->timestamps();
        });

        (new CompetitionTemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_members');
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('competition_templates');
        Schema::dropIfExists('competition_user');
        Schema::dropIfExists('competitions');
    }
};
