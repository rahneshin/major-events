<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 11 — اسپانسرینگ و بازاریابی.
 *
 * - مبلغ‌ها: unsignedBigInteger به «ریال» (هرگز float).
 * - json (نه jsonb) برای سازگاری SQLite/PostgreSQL.
 * - `sponsorship_packages` و جدول واسط `sponsorship_package_asset` نسبت به سند اضافه‌اند:
 *   «بسته‌ی اسپانسری = تجمیع دارایی‌ها» بدون جدول قابل‌پیاده‌سازی نیست (Decision Log، Step 11).
 * - `sponsor_deliverables.event_id` عمداً تکرار شده تا هر query بدون join به رویداد محدود شود (tenant).
 * - `campaign_items.kpi` سند به سه ستون قابل‌سنجش تقسیم شده: kpi_metric / kpi_target / kpi_actual.
 * - شماره‌ی migration عمداً ۵۰ است تا با migrationهای قبلی هم‌پیشوند نشود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsorship_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price')->default(0);       // ریال
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
        });

        Schema::create('sponsorship_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('type', 24);                            // naming|signage|digital|booth|hospitality|media|other
            $table->unsignedInteger('inventory')->default(1);      // تعداد قابل‌ارائه
            $table->unsignedBigInteger('price')->default(0);       // ریال (قیمت هر واحد)
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'type']);
        });

        Schema::create('sponsorship_package_asset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('sponsorship_packages')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('sponsorship_assets')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['package_id', 'asset_id']);
        });

        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('sponsorship_packages')->nullOnDelete();
            $table->string('name', 160);
            $table->string('category', 24)->default('other');
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('status', 16)->default('prospect');    // prospect|negotiating|signed|delivered|lost
            $table->unsignedBigInteger('cash_value')->default(0);     // ریال
            $table->unsignedBigInteger('in_kind_value')->default(0);  // ریال (ارزش ریالی حمایت غیرنقدی)
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
        });

        Schema::create('sponsor_deliverables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('sponsorship_assets')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('commitment', 255);
            $table->unsignedInteger('quantity')->default(1);       // چند واحد از دارایی
            $table->date('due_at')->nullable();
            $table->string('status', 16)->default('pending');      // pending|in_progress|delivered|cancelled
            $table->dateTime('delivered_at')->nullable();
            $table->string('evidence_path')->nullable();           // دیسک خصوصی local
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'due_at']);
            $table->index('sponsor_id');
            $table->index('asset_id');
        });

        Schema::create('marketing_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('target_segments')->nullable();           // لیست متن
            $table->text('positioning')->nullable();
            $table->text('message')->nullable();
            $table->json('channels')->nullable();                  // لیست کلید CampaignChannel
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 8);                            // pre|during|post
            $table->string('channel', 24);
            $table->string('title', 160);
            $table->dateTime('publish_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('planned');      // planned|in_progress|published|cancelled
            $table->string('kpi_metric', 16)->nullable();          // awareness|reach|engagement
            $table->unsignedBigInteger('kpi_target')->nullable();
            $table->unsignedBigInteger('kpi_actual')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['event_id', 'phase']);
            $table->index(['event_id', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_items');
        Schema::dropIfExists('marketing_plans');
        Schema::dropIfExists('sponsor_deliverables');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('sponsorship_package_asset');
        Schema::dropIfExists('sponsorship_assets');
        Schema::dropIfExists('sponsorship_packages');
    }
};
