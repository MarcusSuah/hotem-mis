<?php

namespace App\Enums;

/**
 * Reasons stock changes. Positive types add stock, negative types remove it.
 */
enum InventoryMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Consumption = 'consumption';
    case Adjustment = 'adjustment';
    case Wastage = 'wastage';
    case LaundryOut = 'laundry_out';
    case LaundryIn = 'laundry_in';
    case SupplierReturn = 'supplier_return';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Goods received',
            self::Issue => 'Issued to department',
            self::Consumption => 'Used in maintenance',
            self::Adjustment => 'Stock count adjustment',
            self::Wastage => 'Wastage / expired',
            self::LaundryOut => 'Sent to laundry',
            self::LaundryIn => 'Returned from laundry',
            self::SupplierReturn => 'Returned to supplier',
        };
    }

    /**
     * +1 when the movement adds stock, -1 when it removes stock, 0 when the
     * sign comes from the counted difference (adjustments).
     */
    public function direction(): int
    {
        return match ($this) {
            self::Receipt, self::LaundryIn => 1,
            self::Adjustment => 0,
            default => -1,
        };
    }

    /**
     * Movement types staff may record by hand on the stock screen.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Issue, self::Adjustment, self::Wastage, self::LaundryOut, self::LaundryIn, self::SupplierReturn];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
