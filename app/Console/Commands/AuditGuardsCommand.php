<?php

namespace App\Console\Commands;

use App\Support\AuditGuards;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Reports whether the database itself refuses to alter the audit trail, and can (re)install the guards.
 * Exits non-zero when they are missing, so it can be wired into monitoring or a deploy check.
 */
class AuditGuardsCommand extends Command
{
    protected $signature = 'athar:audit-guards {--install : Create any missing triggers}';

    protected $description = 'Check (or --install) the database-level protection of the activity log';

    public function handle(): int
    {
        if (! AuditGuards::supported()) {
            $this->components->warn('Database-level guards are only installed on MySQL/MariaDB. The application-level guard is active.');

            return self::SUCCESS;
        }

        if ($this->option('install')) {
            try {
                AuditGuards::install();
            } catch (QueryException $e) {
                $this->components->error('The database refused to create the triggers.');
                $this->line($e->getMessage());
                $this->line('');
                $this->line('Usually the database user lacks a privilege while binary logging is on (MySQL error 1419). Either ask');
                $this->line('the database administrator to set log_bin_trust_function_creators=1, or to run this once with a');
                $this->line('privileged account. Nothing else is affected: the application-level guard keeps working.');

                return self::FAILURE;
            }
        }

        $missing = AuditGuards::missing();

        if ($missing === []) {
            $this->components->info('Audit trail is protected: UPDATE and DELETE on activity_logs are blocked by the database.');

            return self::SUCCESS;
        }

        $this->components->error('Missing database trigger(s): '.implode(', ', $missing).'.');
        $this->line('Changes made through the application are still refused, but raw SQL could alter history.');
        $this->line('Run: php artisan athar:audit-guards --install');

        return self::FAILURE;
    }
}
