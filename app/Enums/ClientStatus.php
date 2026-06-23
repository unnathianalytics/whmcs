<?php

namespace App\Enums;

/**
 * What: The lifecycle states a client account can be in.
 * Why: Centralises the allowed statuses, their human labels, and the Flux badge colour for each so the
 *      UI and validation share one source of truth instead of scattering magic strings.
 * When: Used by the Client model cast, the clients list/profile badges, and create/edit validation.
 */
enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Closed = 'closed';

    /**
     * What: Human-readable label for the status.
     * Why: Decouples display text from the stored value.
     * When: Rendered in tables, badges and the status dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
            self::Closed => __('Closed'),
        };
    }

    /**
     * What: The Flux badge colour representing the status.
     * Why: Consistent colour-coding (green=active, yellow=inactive, red=closed) across all screens.
     * When: Passed to `flux:badge :color` wherever a client status is shown.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Inactive => 'yellow',
            self::Closed => 'red',
        };
    }
}
