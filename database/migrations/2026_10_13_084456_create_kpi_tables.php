<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI: targets an administrator sets, achievements the employee fills in, and a score the system
 * works out from the two.
 *
 * A target is stored as a NUMBER plus a direction, never as the text "<90%". The direction is what
 * decides the arithmetic — for "lower is better" a smaller achievement scores higher — so keeping
 * it as prose would make the score unexplainable. `unit` only ever affects display.
 */
return new class extends Migration
{
    public function up(): void
    {
        // What a team's KPIs usually look like, so a period can be generated instead of retyped.
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_value', 20, 4);
            $table->string('direction', 20);
            $table->string('unit', 20)->nullable();
            $table->decimal('weight', 6, 2);
            $table->string('frequency', 20)->default('MONTHLY');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // One employee's KPI sheet for one period.
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Always the first day of the month; the period is a month, not an instant.
            $table->date('period');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('PENDING');
            // Null until there is something to score; recomputed from the items, never typed in.
            $table->decimal('final_score', 8, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();

            // One sheet per person per month; the database, not the application, guarantees it.
            $table->unique(['user_id', 'period']);
            $table->index(['period', 'status']);
        });

        Schema::create('kpi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_value', 20, 4);
            $table->string('direction', 20);
            $table->string('unit', 20)->nullable();
            $table->decimal('weight', 6, 2);
            $table->string('frequency', 20)->default('MONTHLY');
            // What the employee reports. Null means "not filled in yet", which is not the same as 0.
            $table->decimal('achievement', 20, 4)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('kpi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_items');
        Schema::dropIfExists('kpis');
        Schema::dropIfExists('kpi_template_items');
        Schema::dropIfExists('kpi_templates');
    }
};
