<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The database triggers that make activity_logs append-only, in one place so the migration
 * and the `athar:audit-guards` command cannot drift apart. Only MySQL/MariaDB are supported;
 * elsewhere the model guard is all there is.
 */
final class AuditGuards
{
    public const TRIGGERS = ['activity_logs_block_update', 'activity_logs_block_delete'];

    public static function supported(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** @return array<int, string> names of the guard triggers that currently exist */
    public static function present(): array
    {
        return collect(DB::select(
            'SELECT TRIGGER_NAME AS name FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?',
            ['activity_logs'],
        ))->pluck('name')->intersect(self::TRIGGERS)->values()->all();
    }

    /** @return array<int, string> */
    public static function missing(): array
    {
        return array_values(array_diff(self::TRIGGERS, self::present()));
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

        if (in_array('activity_logs_block_update', $missing, true)) {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER activity_logs_block_update BEFORE UPDATE ON activity_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs is append-only'
                SQL);
        }

        if (in_array('activity_logs_block_delete', $missing, true)) {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER activity_logs_block_delete BEFORE DELETE ON activity_logs
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_logs is append-only'
                SQL);
        }
    }

    public static function remove(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
}
