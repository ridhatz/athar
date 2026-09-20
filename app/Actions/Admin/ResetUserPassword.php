<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\AdminAudit;
use App\Services\UserRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Sets a new password for someone (forgotten, or a fresh start) and signs them out everywhere.
 * The new password is returned once to the admin; the audit trail records THAT it happened, never the value.
 */
final class ResetUserPassword
{
    public function __construct(
        private readonly UserRules $rules,
        private readonly AdminAudit $audit,
    ) {}

    /** @return string the new password, in plain text, for the admin to hand over */
    public function handle(User $actor, User $subject, ?string $password = null): string
    {
        Gate::forUser($actor)->authorize('update', $subject);

        $plain = $password ?: Str::password(14, symbols: false);

        DB::transaction(function () use ($actor, $subject, $plain) {
            $subject->forceFill(['password' => $plain])->save();
            $this->rules->revokeAccess($subject);
            $this->audit->record($actor, $subject, 'PASSWORD_RESET');
        });

        return $plain;
    }
}
