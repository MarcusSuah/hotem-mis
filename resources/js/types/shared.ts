export type CurrencyCode = 'USD' | 'LRD';

export type HotelInfo = {
    name: string;
    tagline: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    logo_url: string | null;
    social: Record<string, string | null>;
    url: string;
};

export type CurrencyContext = {
    base: CurrencyCode;
    display: CurrencyCode;
    available: CurrencyCode[];
};

export type MoneyValue = {
    amount: string;
    minor: number;
    currency: CurrencyCode;
    formatted: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type NotificationSummary = {
    unread: number;
    recent: {
        id: string;
        title: string;
        message: string;
        level: string;
        created_at: string | null;
    }[];
};
