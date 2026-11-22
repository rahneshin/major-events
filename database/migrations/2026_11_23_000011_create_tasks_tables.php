<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 08a — tasks, dependencies, comments, attachments, milestones.
 *
 * Portable between SQLite and PostgreSQL (no json/jsonb, no PG-only features).
 *
 * Deliberately NOT here:
 *  - tasks.competition_id  → added with an FK in the Step 12F migration
 *  - FK on tasks.ai_recommendation_id → Step 18 (table does not exist yet)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();
            // one level only (enforced in TaskService); deleting a parent soft-deletes its subtasks
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('phase')->default('pre');          // pre|during|post
            $table->string('module')->default('operations');  // TaskModule
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('todo');        // todo|doing|blocked|done|cancelled
            $table->string('priority')->default('medium');    // low|medium|high|urgent

            $table->timestamp('start_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('estimated_hours', 6, 2)->nullable();

            $table->string('source')->default('manual');      // manual|ai
            $table->unsignedBigInteger('ai_recommendation_id')->nullable(); // no FK until Step 18
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'committee_id']);
            $table->index(['event_id', 'due_at']);
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type')->default('finish_to_start');
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
            $table->index('depends_on_task_id');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['task_id', 'id']);
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // public identifier (no enumeration of ids)
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('local'); // private disk only
            $table->string('path');                   // generated name; never the client's file name
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');       // bytes
            $table->timestamps();

            $table->index('task_id');
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('pending'); // pending|reached|cancelled
            $table->timestamp('reached_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
    }
};
