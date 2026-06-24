<?php

namespace App\Enums;

/**
 * What: The lifecycle states a domain registration can be in.
 * Why: Centralises the allowed statuses, their human labels, and the Flux badge colour for each so the
 *      domains list, client profile and validation share one source of truth instead of scattering magic
 *      strings — mirroring `ServiceStatus`.
 * When: Used by the Domain model cast, the domains list/profile badges, and create/edit/renew validation.
 */
enum DomainStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case PendingTransfer = 'pending-transfer';
    case Cancelled = 'cancelled';

    /**
     * What: Human-readable label for the status.
     * Why: Decouples display text from the stored value.
     * When: Rendered in tables, badges and the status dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Expired => __('Expired'),
            self::PendingTransfer => __('Pending Transfer'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * What: The Flux badge colour representing the status.
     * Why: Consistent colour-coding across all screens (green=active, red=expired, yellow=pending transfer,
     *      zinc=cancelled).
     * When: Passed to `flux:badge :color` wherever a domain status is shown.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Expired => 'red',
            self::PendingTransfer => 'yellow',
            self::Cancelled => 'zinc',
        };
    }
}
