<?php

namespace App\Enums;

/**
 * What: The lifecycle states a support ticket can be in.
 * Why: Centralises the allowed statuses, their human labels, and the Flux badge colour for each so the
 *      ticket list, the thread view and validation share one source of truth instead of scattering magic
 *      strings. `Customer-Reply` is intentionally absent in v1 — there is no client portal, so customers
 *      cannot reply (see the Phase 5 plan); inbound/customer messages are deferred with email piping.
 * When: Used by the Ticket model cast, the ticket list/thread badges, and create/edit validation.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    /**
     * What: Human-readable label for the status.
     * Why: Decouples display text from the stored value.
     * When: Rendered in tables, badges and the status dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Answered => __('Answered'),
            self::Closed => __('Closed'),
        };
    }

    /**
     * What: The Flux badge colour representing the status.
     * Why: Consistent colour-coding across all screens (yellow=open/awaiting action, green=answered,
     *      zinc=closed).
     * When: Passed to `flux:badge :color` wherever a ticket status is shown.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'yellow',
            self::Answered => 'green',
            self::Closed => 'zinc',
        };
    }
}
