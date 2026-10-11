import type { Auth } from '@/types/auth';
import type { CurrencyContext, HotelInfo } from '@/types/shared';

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
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
