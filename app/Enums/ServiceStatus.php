<?php

namespace App\Enums;

/**
 * What: The lifecycle states a client service instance can be in.
 * Why: Centralises the allowed statuses, their human labels, and the Flux badge colour for each so the
 *      services list, client profile and validation share one source of truth instead of scattering
 *      magic strings.
 * When: Used by the ClientService model cast, the services list/profile badges, and assign/edit validation.
 */
enum ServiceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * What: Human-readable label for the status.
     * Why: Decouples display text from the stored value.
     * When: Rendered in tables, badges and the status dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('Expired'),
        };
    }

    /**
     * What: The Flux badge colour representing the status.
     * Why: Consistent colour-coding across all screens (green=active, zinc=pending, yellow=suspended,
     *      red=cancelled/expired).
     * When: Passed to `flux:badge :color` wherever a service status is shown.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'zinc',
            self::Suspended => 'yellow',
            self::Cancelled => 'red',
            self::Expired => 'red',
        };
    }
}
