import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { hotel } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                {hotel.logo_url ? (
                    <img
                        src={hotel.logo_url}
                        alt=""
                        className="size-full rounded-md object-cover"
                    />
                ) : (
                    <AppLogoIcon className="size-5 fill-current" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate font-display text-base leading-tight font-semibold">
                    {hotel.name}
                </span>
                <span className="truncate text-[10px] tracking-[0.2em] text-sidebar-foreground/60 uppercase">
                    Management Suite
                </span>
            </div>
        </>
    );
}
