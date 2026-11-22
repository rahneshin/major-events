<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 09 — بودجه و درآمد.
 *
 * - مبلغ‌ها: unsignedBigInteger به «ریال» (هرگز float).
 * - json (نه jsonb) برای سازگاری SQLite/PostgreSQL.
 * - شماره‌ی migration عمداً ۲۰ است تا با migrationهای Step 08 هم‌پیشوند نشود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);                       // expense|revenue
            $table->string('category', 32);
            $table->string('name', 160);
            $table->string('cost_behavior', 16)->default('fixed'); // fixed|variable
            $table->unsignedBigInteger('planned_amount')->default(0); // ریال
            $table->text('assumption')->nullable();
            $table->unsignedInteger('version')->default(1);   // نسخه‌ی کاری هنگام آخرین تغییر
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'type']);
            $table->index(['event_id', 'committee_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('budget_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');              // ریال؛ هزینه‌ی انجام‌شده یا درآمد دریافت‌شده
            $table->date('date');
            $table->string('description', 255)->nullable();
            $table->string('receipt_path')->nullable();        // دیسک خصوصی local
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['budget_line_id', 'date']);
        });

        Schema::create('budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 16)->default('draft');    // draft|approved|superseded
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();               // خطوط بودجه هنگام تأیید
            $table->unsignedBigInteger('total_expense')->nullable();
            $table->unsignedBigInteger('total_revenue')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('budget_lines');
    }
};
