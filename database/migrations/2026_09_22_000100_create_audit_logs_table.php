<?php

use App\Support\AuditGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Who changed what about the people and setup of the system (users, roles, teams…).
 * Request history lives in activity_logs; this is the same idea for administration.
 * Append-only, like activity_logs. Secrets (passwords) are never stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 40);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index('actor_id');
        });

        if (AuditGuards::supported()) {
            try {
                AuditGuards::install();
            } catch (QueryException $e) {
                Log::warning('Athar: could not create audit_logs triggers; relying on the model guard only.', ['reason' => $e->getMessage()]);
            }
        }
    }

    public function down(): void
    {
        if (AuditGuards::supported()) {
            AuditGuards::remove();
        }

        Schema::dropIfExists('audit_logs');
    }
};
