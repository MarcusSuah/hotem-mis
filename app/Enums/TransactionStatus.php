<?php

namespace App\Enums;

/**
 * Status of a payment or refund. Only completed transactions affect balances.
 */
enum TransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
