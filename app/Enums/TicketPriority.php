<?php

namespace App\Enums;

/**
 * What: The urgency levels a support ticket can carry.
 * Why: Centralises the allowed priorities, their human labels, and the Flux badge colour for each so the
 *      ticket list, the thread view and validation share one source of truth instead of scattering magic
 *      strings.
 * When: Used by the Ticket model cast, the ticket list/thread badges, and create/edit validation.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /**
     * What: Human-readable label for the priority.
     * Why: Decouples display text from the stored value.
     * When: Rendered in tables, badges and the priority dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
            self::Urgent => __('Urgent'),
        };
    }

    /**
     * What: The Flux badge colour representing the priority.
     * Why: Consistent colour-coding across all screens (zinc=low, sky=medium, amber=high, red=urgent).
     * When: Passed to `flux:badge :color` wherever a ticket priority is shown.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'zinc',
            self::Medium => 'sky',
            self::High => 'amber',
            self::Urgent => 'red',
        };
    }
}
