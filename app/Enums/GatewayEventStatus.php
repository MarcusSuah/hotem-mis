<?php

namespace App\Enums;

/**
 * Processing state of a payment-gateway webhook event.
 */
enum GatewayEventStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Waiting',
            self::Processed => 'Processed',
            self::Ignored => 'Ignored',
            self::Failed => 'Needs attention',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
