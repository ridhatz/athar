<?php

namespace App\Events;

use App\Models\ServiceRequest;
use App\Models\User;

/**
 * "Something happened to a request that someone may need to know about."
 *
 * Business code (the Actions) only raises this; it never decides who is told, or how.
 * A listener turns it into notifications, so channels can change without touching the workflow.
 */
final class RequestNotable
{
    public const ASSIGNED = 'assigned';

    public const STATUS_CHANGED = 'status_changed';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $kind,
        public readonly ServiceRequest $request,
        public readonly ?User $actor,
        public readonly array $context = [],
    ) {}
}
