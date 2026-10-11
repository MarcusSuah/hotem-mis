import { Link, usePage } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, logout, register } from '@/routes';
import account from '@/routes/account';

/**
 * Public-facing shell used by the website and the guest account area.
 */
export default function SiteLayout({ children }: { children: ReactNode }) {
    const { hotel, auth } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <header className="sticky top-0 z-40 border-b border-white/10 bg-teal text-ivory">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
                    <Link
                        href={home()}
                        className="flex items-center gap-2.5"
                        aria-label={`${hotel.name} home`}
                    >
                        <span className="flex size-9 items-center justify-center rounded-md bg-gold text-teal">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        <span className="hidden font-display text-xl font-semibold sm:inline">
                            {hotel.name}
                        </span>
                    </Link>

                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <>
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="text-ivory hover:bg-white/10 hover:text-white"
                                >
                                    <Link
                                        href={
                                            auth.user.is_staff
                                                ? dashboard()
                                                : account.dashboard()
                                        }
                                    >
                                        {auth.user.is_staff
                                            ? 'Back office'
                                            : 'My account'}
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="text-ivory/80 hover:bg-white/10 hover:text-white"
                                >
                                    <Link href={logout()} as="button">
                                        Log out
                                    </Link>
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="text-ivory hover:bg-white/10 hover:text-white"
                                >
                                    <Link href={login()}>Sign in</Link>
                                </Button>
                                <Button asChild variant="gold">
                                    <Link href={register()}>Join</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="bg-teal text-ivory/80">
                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3">
                    <div className="space-y-3">
                        <p className="font-display text-2xl text-ivory">
                            {hotel.name}
                        </p>
                        {hotel.tagline && (
                            <p className="text-sm">{hotel.tagline}</p>
                        )}
                    </div>
                    <address className="space-y-2 text-sm not-italic">
                        {hotel.address && (
                            <p className="flex items-start gap-2">
                                <MapPin className="mt-0.5 size-4 shrink-0 text-gold" />
                                {hotel.address}
                            </p>
                        )}
                        {hotel.phone && (
                            <p className="flex items-center gap-2">
                                <Phone className="size-4 shrink-0 text-gold" />
                                <a
                                    href={`tel:${hotel.phone}`}
                                    className="hover:text-ivory"
                                >
                                    {hotel.phone}
                                </a>
                            </p>
                        )}
                        {hotel.email && (
                            <p className="flex items-center gap-2">
                                <Mail className="size-4 shrink-0 text-gold" />
                                <a
                                    href={`mailto:${hotel.email}`}
                                    className="hover:text-ivory"
                                >
                                    {hotel.email}
                                </a>
                            </p>
                        )}
                    </address>
                    <p className="text-sm md:text-right">
                        © {new Date().getFullYear()} {hotel.name}. All rights
                        reserved.
                    </p>
                </div>
            </footer>
        </div>
    );
}
