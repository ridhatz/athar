<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Communication Behavior Assessment: a fixed 24-item forced-choice instrument (the
 * organization's own PDF, replicated question for question — see AssessmentQuestions).
 *
 * Every row is a full submission, never edited in place: retaking creates a NEW row rather than
 * overwriting the last one, so a person's history of how they answered over time is never lost —
 * the same append-only stance Athar takes everywhere else. "Their result" means their latest row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // The 24 raw choices (column index 0=D,1=I,2=S,3=C for both "most" and "least" per
            // item), kept alongside the computed scores so the scoring rule can be re-derived
            // later without having lost what the person actually answered.
            $table->json('answers');

            // Net score per dimension (times chosen "most" minus times chosen "least"); the four
            // always sum to zero, so each ranges -24..24 in practice.
            $table->smallInteger('score_d')->default(0);
            $table->smallInteger('score_i')->default(0);
            $table->smallInteger('score_s')->default(0);
            $table->smallInteger('score_c')->default(0);

            // The single highest-scoring dimension, for a quick label ("Gaya dominan: Influence").
            $table->string('dominant_style', 1);

            // sales_index = D+I, marketing_index = I+C — see AssessmentScoring for the reasoning.
            $table->smallInteger('sales_index')->default(0);
            $table->smallInteger('marketing_index')->default(0);
            $table->string('recommendation', 20);

            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
