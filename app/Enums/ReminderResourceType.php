<?php

namespace App\Enums;

use App\Models\ClientService;
use App\Models\Domain;

/**
 * What: The kind of expiring resource a reminder rule targets — a client service or a domain.
 * Why: Reminder rules are scoped by resource type only in v1 (no per-product-group targeting), so this enum
 *      is the single source of truth for the allowed types, their labels, and the model class the dispatcher
 *      scans for each type. Centralising it keeps the rule form, the dispatcher and validation in sync.
 * When: Used by the ReminderRule cast, the rules CRUD form, and the SendExpiryReminders dispatcher when it
 *       resolves which `expires_at`-bearing model to query for a rule.
 */
enum ReminderResourceType: string
{
    case Service = 'service';
    case Domain = 'domain';

    /**
     * What: Human-readable label for the resource type.
     * Why: Decouples display text from the stored value for the rules table and form.
     * When: Rendered in the reminder rules list and the resource-type dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Service => __('Service'),
            self::Domain => __('Domain'),
        };
    }

    /**
     * What: The Eloquent model class whose `expires_at` this type scans.
     * Why: Lets the dispatcher resolve the right table from a rule without a hard-coded match at the call
     *      site, so both the scheduled command and the manual "send now" action share one mapping.
     * When: Called by the reminder dispatcher when building the per-rule expiry query.
     *
     * @return class-string<ClientService|Domain>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Service => ClientService::class,
            self::Domain => Domain::class,
        };
    }
}
