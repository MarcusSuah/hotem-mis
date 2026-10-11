<?php

namespace App\Services\Billing;

use App\Support\Money\Money;

/**
 * Derived totals of a folio, all in the folio currency.
 */
final readonly class FolioSummary
{
    /**
     * @param  array<string, Money>  $byCategory
     */
    public function __construct(
        public Money $charges,
        public Money $payments,
        public Money $refunds,
        public Money $balance,
        public array $byCategory,
    ) {}

    /**
     * Net money held from the guest (payments minus refunds).
     */
    public function netPaid(): Money
    {
        return $this->payments->subtract($this->refunds);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'charges' => $this->charges,
            'payments' => $this->payments,
            'refunds' => $this->refunds,
            'net_paid' => $this->netPaid(),
            'balance' => $this->balance,
            'by_category' => $this->byCategory,
        ];
    }
}
