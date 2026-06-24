<?php

namespace App\Enums;

/**
 * What: The ways a payment (transaction) against an invoice can be recorded.
 * Why: Centralises the allowed payment methods and their human labels so the record-payment form and the
 *      transaction list share one source of truth instead of scattering magic strings. Payments are logged
 *      manually in v1 (no gateway integration), so these are descriptive only.
 * When: Used by the Transaction model cast, the record-payment form, and the transaction list.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank-transfer';
    case Card = 'card';
    case Online = 'online';
    case Cheque = 'cheque';
    case Other = 'other';

    /**
     * What: Human-readable label for the payment method.
     * Why: Decouples display text from the stored value.
     * When: Rendered in the transaction list and the record-payment dropdown.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::BankTransfer => __('Bank Transfer'),
            self::Card => __('Card'),
            self::Online => __('Online'),
            self::Cheque => __('Cheque'),
            self::Other => __('Other'),
        };
    }
}
