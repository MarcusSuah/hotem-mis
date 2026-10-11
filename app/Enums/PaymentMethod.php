<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case MobileMoney = 'mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank transfer',
            self::Card => 'Card (terminal)',
            self::MobileMoney => 'Mobile money',
        };
    }

    /**
     * Non-cash payments must carry the provider's transaction reference.
     */
    public function requiresReference(): bool
    {
        return $this !== self::Cash;
    }
}
