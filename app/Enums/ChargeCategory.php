<?php

namespace App\Enums;

/**
 * Folio line categories. Tax is a liability collected for the government and
 * is never counted as hotel revenue in reports.
 */
enum ChargeCategory: string
{
    case Room = 'room';
    case ServiceCharge = 'service_charge';
    case Tax = 'tax';
    case EarlyCheckIn = 'early_check_in';
    case LateCheckOut = 'late_check_out';
    case FoodAndBeverage = 'food_beverage';
    case Minibar = 'minibar';
    case Laundry = 'laundry';
    case Spa = 'spa';
    case Transport = 'transport';
    case Service = 'service';
    case Damage = 'damage';
    case CancellationFee = 'cancellation_fee';
    case NoShowFee = 'no_show_fee';
    case Discount = 'discount';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Accommodation',
            self::ServiceCharge => 'Service charge',
            self::Tax => 'Tax',
            self::EarlyCheckIn => 'Early check-in',
            self::LateCheckOut => 'Late check-out',
            self::FoodAndBeverage => 'Food & beverage',
            self::Minibar => 'Minibar',
            self::Laundry => 'Laundry',
            self::Spa => 'Spa',
            self::Transport => 'Transport',
            self::Service => 'Guest service',
            self::Damage => 'Damage',
            self::CancellationFee => 'Cancellation fee',
            self::NoShowFee => 'No-show fee',
            self::Discount => 'Discount',
            self::Adjustment => 'Adjustment',
        };
    }

    public function isRevenue(): bool
    {
        return $this !== self::Tax;
    }

    /**
     * Categories staff may post manually from the folio screen.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [
            self::FoodAndBeverage, self::Minibar, self::Laundry, self::Spa, self::Transport,
            self::Service, self::Damage, self::EarlyCheckIn, self::LateCheckOut, self::Discount, self::Adjustment,
        ];
    }
}
