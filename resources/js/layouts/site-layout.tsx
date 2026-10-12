import { Link, usePage } from '@inertiajs/react';
import { Facebook, Instagram, Mail, MapPin, Menu, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard, home, login, logout, register } from '@/routes';
import account from '@/routes/account';
import site from '@/routes/site';

const navigation = [
    { title: 'Rooms', href: site.rooms.index.url() },
    { title: 'Offers', href: site.offers.index.url() },
    { title: 'Dining', href: site.dining.url() },
    { title: 'Experiences', href: site.experiences.url() },
    { title: 'Events', href: site.events.index.url() },
    { title: 'Journal', href: site.blog.index.url() },
    { title: 'Gallery', href: site.gallery.url() },
    { title: 'Contact', href: site.contact.url() },
];

/**
 * Public-facing shell used by the website and the guest account area.
 */
export default function SiteLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const { hotel, auth, sitePages } = page.props;
    const path = page.url.split('?')[0];
    const isActive = (href: string) =>
        path === href || path.startsWith(`${href}/`);

    const accountLinks = auth.user ? (
        <>
            <Button
                asChild
                variant="ghost"
                className="text-ivory hover:bg-white/10 hover:text-white"
            >
                <Link
                    href={
                        auth.user.is_staff ? dashboard() : account.dashboard()
                    }
                >
                    {auth.user.is_staff ? 'Back office' : 'My account'}
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
        <Button
            asChild
            variant="ghost"
            className="text-ivory hover:bg-white/10 hover:text-white"
        >
            <Link href={login()}>Sign in</Link>
        </Button>
    );

    return (
        <div className="flex min-h-screen flex-col bg-background">
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded focus:bg-gold focus:px-3 focus:py-2 focus:text-teal"
            >
                Skip to content
            </a>
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

                    <nav
                        aria-label="Main"
                        className="hidden items-center gap-1 xl:flex"
                    >
                        {navigation.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm text-ivory/80 transition hover:bg-white/10 hover:text-white',
                                    isActive(item.href) && 'text-gold',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>

                    <div className="flex items-center gap-1">
                        <div className="hidden items-center gap-1 md:flex">
                            {accountLinks}
                        </div>
                        <Button
                            asChild
                            variant="gold"
                            className="hidden sm:inline-flex"
                        >
                            <Link href={site.booking.start()}>
                                Book your stay
                            </Link>
                        </Button>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="text-ivory hover:bg-white/10 hover:text-white xl:hidden"
                                    aria-label="Open menu"
                                >
                                    <Menu />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="right"
                                className="w-72 bg-teal text-ivory"
                            >
                                <SheetTitle className="px-4 pt-4 font-display text-xl text-ivory">
                                    {hotel.name}
                                </SheetTitle>
                                <nav
                                    aria-label="Mobile"
                                    className="flex flex-col gap-1 px-2"
                                >
                                    {navigation.map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            className={cn(
                                                'rounded-md px-3 py-2 text-ivory/80 hover:bg-white/10 hover:text-white',
                                                isActive(item.href) &&
                                                    'text-gold',
                                            )}
                                        >
                                            {item.title}
                                        </Link>
                                    ))}
                                </nav>
                                <div className="mt-auto flex flex-col gap-2 p-4">
                                    <Button asChild variant="gold">
                                        <Link href={site.booking.start()}>
                                            Book your stay
                                        </Link>
                                    </Button>
                                    {auth.user ? (
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="border-white/30 bg-transparent text-ivory"
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
                                    ) : (
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="border-white/30 bg-transparent text-ivory"
                                        >
                                            <Link href={login()}>Sign in</Link>
                                        </Button>
                                    )}
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>
            </header>

            <main id="main" className="flex-1">
                {children}
            </main>

            <footer className="bg-teal text-ivory/80">
                <div className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-4">
                    <div className="space-y-3 md:col-span-2">
                        <p className="font-display text-3xl text-ivory">
                            {hotel.name}
                        </p>
                        {hotel.tagline && (
                            <p className="max-w-sm text-sm">{hotel.tagline}</p>
                        )}
                        <div className="flex gap-3 pt-2">
                            {hotel.social.facebook && (
                                <a
                                    href={hotel.social.facebook}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Facebook"
                                    className="hover:text-gold"
                                >
                                    <Facebook className="size-5" />
                                </a>
                            )}
                            {hotel.social.instagram && (
                                <a
                                    href={hotel.social.instagram}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Instagram"
                                    className="hover:text-gold"
                                >
                                    <Instagram className="size-5" />
                                </a>
                            )}
                        </div>
                        {!auth.user && (
                            <p className="pt-2 text-sm">
                                <Link
                                    href={register()}
                                    className="text-gold hover:underline"
                                >
                                    Create an account
                                </Link>{' '}
                                to manage your bookings and leave reviews.
                            </p>
                        )}
                    </div>
                    <nav aria-label="Footer" className="space-y-2 text-sm">
                        <p className="font-semibold text-ivory">Explore</p>
                        <ul className="space-y-1.5">
                            <li>
                                <Link
                                    href={site.reviews()}
                                    className="hover:text-ivory"
                                >
                                    Guest reviews
                                </Link>
                            </li>
                            {sitePages.map((item) => (
                                <li key={item.slug}>
                                    <Link
                                        href={site.pages.show(item.slug)}
                                        className="hover:text-ivory"
                                    >
                                        {item.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                    <address className="space-y-2 text-sm not-italic">
                        <p className="font-semibold text-ivory">Visit</p>
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
                </div>
                <div className="border-t border-white/10">
                    <p className="mx-auto max-w-7xl px-4 py-4 text-xs sm:px-6">
                        © {new Date().getFullYear()} {hotel.name}. All rights
                        reserved.
                    </p>
                </div>
            </footer>
        </div>
    );
}
