<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Human-readable, gap-free, never-reused request numbers: ATH-000001, ATH-000002, …
 *
 * The next value is taken by locking the counter row for the rest of the caller's
 * transaction. Two concurrent creators therefore queue up instead of racing, and if the
 * creating transaction rolls back the number is handed out again, so there are no holes.
 * Must be called inside a transaction (CreateRequest does).
 */
final class RequestNumberGenerator
{
    public const COUNTER = 'request_number';

    public const PREFIX = 'ATH-';

    public function next(): string
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('RequestNumberGenerator::next() must run inside a database transaction.');
        }

        // The migration seeds this row; this only covers someone having emptied the table.
        DB::table('counters')->insertOrIgnore(['name' => self::COUNTER, 'value' => 0]);

        $current = (int) DB::table('counters')->where('name', self::COUNTER)->lockForUpdate()->value('value');
        $next = $current + 1;

        DB::table('counters')->where('name', self::COUNTER)->update(['value' => $next]);

        return self::PREFIX.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
