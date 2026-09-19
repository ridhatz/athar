<?php

use App\Support\AuditGuards;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Database-level immutability for the audit trail.
 *
 * The ActivityLog model already refuses updates and deletes, but that only protects code paths
 * that go through Eloquent. These triggers also stop raw queries, mass updates, and anyone with a
 * database console.
 *
 * MySQL refuses trigger creation for non-SUPER users while binary logging is on (error 1419)
 * unless log_bin_trust_function_creators=1, and some hosts forbid triggers outright. Then we do
 * NOT fail the deployment: the model guard stays active and this is logged.
 * `php artisan athar:audit-guards` shows the state; `--install` retries once the server allows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! AuditGuards::supported()) {
            return;
        }

        try {
            AuditGuards::install();
        } catch (QueryException $e) {
            Log::warning('Athar: could not create audit-log triggers; relying on the model guard only.', [
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        if (AuditGuards::supported()) {
            AuditGuards::remove();
        }
    }
};
