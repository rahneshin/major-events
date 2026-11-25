<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 12B — مرحله‌ها، خانه‌های قرعه، سند قرعه، بازی‌ها. (گیم/سری‌ها برای 12C/12E از همین حالا ساخته می‌شوند.)
 * مدل بازی `SportMatch` روی جدول `matches` است (`Match` در PHP رزرو است).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name', 120);
            $table->string('format', 16); // round_robin | single_elim | double_elim | groups | heats
            $table->json('config')->nullable();
            $table->boolean('is_final')->default(false);
            $table->string('status', 12)->default('pending');
            $table->timestamps();

            $table->unique(['competition_id', 'position']);
        });

        Schema::create('stage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->string('group_name', 16)->nullable();
            $table->unsignedInteger('seed')->nullable();
            $table->unsignedInteger('draw_position')->nullable();
            $table->unsignedInteger('final_rank')->nullable();

            $table->unique(['stage_id', 'competitor_id']);
        });

        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->string('method', 12); // seeded | random | pots
            $table->string('rng_seed', 64);
            $table->json('constraints')->nullable();
            $table->json('result');
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_match_id')->nullable()->constrained('matches')->cascadeOnDelete();
            $table->string('group_name', 16)->nullable();
            $table->unsignedInteger('round');
            $table->unsignedInteger('leg')->default(1);
            $table->unsignedInteger('number');
            $table->string('bracket', 16)->nullable();
            $table->foreignId('a_id')->nullable()->constrained('competitors')->nullOnDelete();
            $table->foreignId('b_id')->nullable()->constrained('competitors')->nullOnDelete();
            $table->json('a_source')->nullable();
            $table->json('b_source')->nullable();
            $table->foreignId('next_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->string('next_slot', 1)->nullable();
            $table->foreignId('loser_next_match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->string('loser_next_slot', 1)->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('court', 60)->nullable();
            $table->string('status', 12)->default('scheduled');
            $table->foreignId('winner_id')->nullable()->constrained('competitors')->nullOnDelete();
            $table->unsignedInteger('score_a')->nullable();
            $table->unsignedInteger('score_b')->nullable();
            $table->unsignedInteger('points_a')->nullable();
            $table->unsignedInteger('points_b')->nullable();
            $table->string('result_status', 12)->nullable();
            $table->boolean('counts_in_standings')->default(true);
            $table->boolean('is_manual')->default(false);
            $table->json('result_meta')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['stage_id', 'round']);
            $table->index(['stage_id', 'status']);
        });

        Schema::create('match_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->unsignedInteger('game_no');
            $table->unsignedInteger('points_a');
            $table->unsignedInteger('points_b');

            $table->unique(['match_id', 'game_no']);
        });

        Schema::create('heats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('position');
            $table->dateTime('scheduled_at')->nullable();
        });

        Schema::create('heat_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 12, 3)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->string('status', 8)->default('ok');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_results');
        Schema::dropIfExists('heats');
        Schema::dropIfExists('match_games');
        Schema::dropIfExists('matches');
        Schema::dropIfExists('draws');
        Schema::dropIfExists('stage_entries');
        Schema::dropIfExists('stages');
    }
};
