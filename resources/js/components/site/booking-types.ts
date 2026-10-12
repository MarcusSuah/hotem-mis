import type { MoneyValue } from '@/types';

export type StayQuote = {
    nights: {
        date: string;
        amount: MoneyValue;
        service_charge: MoneyValue;
        tax: MoneyValue;
        base: MoneyValue;
    }[];
    room_total: MoneyValue;
    service_charge: MoneyValue;
    tax: MoneyValue;
    total: MoneyValue;
    base_total: MoneyValue;
    deposit_due: MoneyValue;
    currency: string;
    base_currency: string;
    exchange_rate: string | null;
    tax_rate: string;
    service_charge_rate: string;
};

export type BookingCriteria = {
    check_in_date: string;
    check_out_date: string;
    adults: number;
    children: number;
    offer: string | null;
};

export function criteriaQuery(
    criteria: BookingCriteria,
): Record<string, string | number> {
    const query: Record<string, string | number> = {
        check_in_date: criteria.check_in_date,
        check_out_date: criteria.check_out_date,
        adults: criteria.adults,
        children: criteria.children,
    };

    if (criteria.offer) {
        query.offer = criteria.offer;
    }

    return query;
}
