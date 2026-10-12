import type { Auth } from '@/types/auth';
import type {
    CurrencyContext,
    HotelInfo,
    NotificationSummary,
} from '@/types/shared';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            hotel: HotelInfo;
            auth: Auth;
            currency: CurrencyContext;
            notifications: NotificationSummary | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
