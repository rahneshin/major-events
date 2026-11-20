<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('sport_type')->nullable();
            $table->string('event_type')->default('other');
            $table->string('scale')->default('medium');
            $table->string('status')->default('draft')->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->unsignedInteger('expected_participants')->nullable();
            $table->unsignedInteger('expected_spectators')->nullable();
            $table->unsignedBigInteger('budget_estimate')->nullable(); // rials
            $table->string('main_goal_type')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'slug']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('event_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('secondary'); // main|secondary
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('priority')->default(1);
            $table->string('smart_measure')->nullable();
            $table->string('target_value')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'kind']);
        });

        Schema::create('event_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_stakeholders');
        Schema::dropIfExists('event_goals');
        Schema::dropIfExists('events');
    }
};
