<?php

namespace App\Enums;

/**
 * The lifecycle of a request. The transition table lives here, in one place,
 * so that the rules are explicit and never re-implemented in controllers or UI.
 *
 * NEW → TRIAGED → ASSIGNED → IN_PROGRESS → REVIEW → COMPLETED
 *
 * BLOCKED may be entered from any working state and resumes to the state it was
 * blocked from (stored on the request). CANCELLED is reachable from any open state.
 * COMPLETED and CANCELLED are terminal.
 */
enum RequestStatus: string
{
    case New = 'NEW';
    case Triaged = 'TRIAGED';
    case Assigned = 'ASSIGNED';
    case InProgress = 'IN_PROGRESS';
    case Review = 'REVIEW';
    case Completed = 'COMPLETED';
    case Blocked = 'BLOCKED';
    case Cancelled = 'CANCELLED';

    /**
     * Statuses this one may move to. BLOCKED is dynamic (it resumes to the state it
     * was blocked from), so it returns only CANCELLED here; see RequestWorkflow::targets().
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::New => [self::Triaged, self::Cancelled],
            self::Triaged => [self::Assigned, self::Cancelled],
            self::Assigned => [self::InProgress, self::Blocked, self::Cancelled],
            self::InProgress => [self::Review, self::Blocked, self::Cancelled],
            self::Review => [self::Completed, self::InProgress, self::Blocked, self::Cancelled],
            self::Blocked => [self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** A request that is still open (not completed and not cancelled). */
    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }

    /** States in which someone is actively working, and from which a request may be blocked. */
    public function isWorking(): bool
    {
        return in_array($this, [self::Assigned, self::InProgress, self::Review], true);
    }

    /** States that make no sense without a named person (PIC) responsible. */
    public function requiresPic(): bool
    {
        return $this === self::InProgress || $this === self::Review;
    }

    /** Transitions into these states must carry an explanation. */
    public function requiresNote(): bool
    {
        return $this === self::Blocked || $this === self::Cancelled;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
