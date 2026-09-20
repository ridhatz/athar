<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The database triggers that make the audit tables append-only, in one place so the migrations
 * and the `athar:audit-guards` command cannot drift apart. Only MySQL/MariaDB are supported;
 * elsewhere the model guards are all there is.
 */
final class AuditGuards
{
    /** table => the two trigger names guarding it */
    private const TABLES = [
        'activity_logs' => ['activity_logs_block_update', 'activity_logs_block_delete'],
        'audit_logs' => ['audit_logs_block_update', 'audit_logs_block_delete'],
    ];

    public const TRIGGERS = [
        'activity_logs_block_update', 'activity_logs_block_delete',
        'audit_logs_block_update', 'audit_logs_block_delete',
    ];

    public static function supported(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** @return array<int, string> names of the guard triggers that currently exist */
    public static function present(): array
    {
        return collect(DB::select(
            'SELECT TRIGGER_NAME AS name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()',
        ))->pluck('name')->intersect(self::TRIGGERS)->values()->all();
    }

    /**
     * Guards that should exist but do not. A table that has not been created yet is not "missing":
     * its migration will install its own guards.
     *
     * @return array<int, string>
     */
    public static function missing(): array
    {
        $expected = [];

        foreach (self::TABLES as $table => $names) {
            if (Schema::hasTable($table)) {
                array_push($expected, ...$names);
            }
        }

        return array_values(array_diff($expected, self::present()));
    }

    /**
     * Create whichever triggers are missing. Throws the database's own error (typically
     * 1419: no SUPER privilege while binary logging is on) so the caller can decide what to do.
     *
     * @throws QueryException
     */
    public static function install(): void
    {
        $missing = self::missing();

        foreach (self::TABLES as $table => [$onUpdate, $onDelete]) {
            if (in_array($onUpdate, $missing, true)) {
                DB::unprepared("CREATE TRIGGER {$onUpdate} BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} is append-only'");
            }

            if (in_array($onDelete, $missing, true)) {
                DB::unprepared("CREATE TRIGGER {$onDelete} BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} is append-only'");
            }
        }
    }

    public static function remove(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
}
