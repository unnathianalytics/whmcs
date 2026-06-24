<?php

namespace App\Enums;

/**
 * What: The lifecycle states an invoice can be in.
 * Why: Centralises the allowed statuses, their human labels, and the Flux badge colour for each so the
 *      invoice list, the invoice detail page and validation share one source of truth instead of
 *      scattering magic strings.
 * When: Used by the Invoice model cast, the invoice list/detail badges, and create/edit validation.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /**
     * What: Human-readable label for the status.
     * Why: Decouples display text from the stored value.
     * When: Rendered in tables, badges and the status dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Unpaid => __('Unpaid'),
            self::Paid => __('Paid'),
            self::Overdue => __('Overdue'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * What: The Flux badge colour representing the status.
     * Why: Consistent colour-coding across all screens (green=paid, yellow=unpaid, red=overdue,
     *      zinc=draft/cancelled).
     * When: Passed to `flux:badge :color` wherever an invoice status is shown.
     */
    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Unpaid => 'yellow',
            self::Overdue => 'red',
            self::Draft => 'zinc',
            self::Cancelled => 'zinc',
        };
    }
}
