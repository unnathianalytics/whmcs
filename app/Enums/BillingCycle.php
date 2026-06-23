<?php

namespace App\Enums;

/**
 * What: The recurring billing terms a product price or client service can run on.
 * Why: Centralises the allowed cycles, their human labels, and the cycle length in months so pricing,
 *      service assignment and the (Phase 7) reminder/date logic share one source of truth instead of
 *      scattering magic strings.
 * When: Used by the ProductPricing `cycle` cast, the ClientService `billing_cycle` cast, and the
 *       create/edit forms for both products and services.
 */
enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi-annual';
    case Annual = 'annual';
    case Biennial = 'biennial';
    case Triennial = 'triennial';
    case OneTime = 'one-time';

    /**
     * What: Human-readable label for the billing cycle.
     * Why: Decouples display text from the stored value.
     * When: Rendered in tables, badges and cycle dropdowns.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Quarterly => __('Quarterly'),
            self::SemiAnnual => __('Semi-Annual'),
            self::Annual => __('Annual'),
            self::Biennial => __('Biennial'),
            self::Triennial => __('Triennial'),
            self::OneTime => __('One-Time'),
        };
    }

    /**
     * What: The length of one cycle in months (null for one-time).
     * Why: Lets the service form pre-fill a sensible `expires_at` / `next_due_date` from `starts_at`
     *      without forcing it — the admin still enters the dates manually and can override the hint.
     * When: Read when defaulting service dates on the assign form.
     */
    public function months(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::SemiAnnual => 6,
            self::Annual => 12,
            self::Biennial => 24,
            self::Triennial => 36,
            self::OneTime => null,
        };
    }
}
