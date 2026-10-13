<?php

namespace App\Actions\Kpi;

use App\Enums\KpiStatus;
use App\Models\Kpi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A supervisor accepts the sheet or sends it back.
 *
 * Sending back returns it to the employee's hands without erasing anything: the numbers stay, the
 * note says what to fix, and submitting again starts a fresh review.
 */
final class ReviewKpi
{
    /**
     * @param  bool  $approved  true to accept, false to ask for a revision
     */
    public function handle(User $actor, Kpi $kpi, bool $approved, ?string $note = null): Kpi
    {
        Gate::forUser($actor)->authorize('review', $kpi);

        $note = trim((string) $note);

        // Accepting needs no explanation; sending work back always does.
        if (! $approved && $note === '') {
            throw ValidationException::withMessages(['review_note' => __('errors.note_required')]);
        }

        return DB::transaction(function () use ($actor, $kpi, $approved, $note) {
            $kpi = Kpi::query()->whereKey($kpi->id)->lockForUpdate()->firstOrFail();

            if (! $kpi->status->awaitsReview()) {
                throw ValidationException::withMessages(['status' => __('errors.kpi_not_in_review')]);
            }

            $kpi->status = $approved ? KpiStatus::Reviewed : KpiStatus::Revision;
            $kpi->reviewed_at = now();
            $kpi->reviewed_by = $actor->id;
            $kpi->review_note = $note === '' ? null : $note;
            $kpi->save();

            return $kpi->load('items', 'user', 'reviewer');
        });
    }
}
