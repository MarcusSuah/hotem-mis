import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Access restricted',
        description:
            'Your account does not have permission to view this page. If you believe this is a mistake, please contact the hotel administrator.',
    },
    404: {
        title: 'Page not found',
        description:
            'The page you are looking for has moved or no longer exists.',
    },
    419: {
        title: 'Session expired',
        description: 'Your session expired. Please refresh and try again.',
    },
    500: {
        title: 'Something went wrong',
        description:
            'An unexpected error occurred on our side. Our team has been notified; please try again shortly.',
    },
    503: {
        title: 'Briefly unavailable',
        description:
            'We are performing scheduled maintenance. Please check back soon.',
    },
};

export default function ErrorPage({ status }: { status: number }) {
    const { auth, hotel } = usePage().props;
    const message = messages[status] ?? messages[500];

    return (
        <>
            <Head title={message.title} />
            <div className="flex min-h-screen flex-col items-center justify-center bg-teal px-6 text-center text-ivory">
                <span className="mb-8 flex size-14 items-center justify-center rounded-xl bg-gold text-teal">
                    <AppLogoIcon className="size-8 fill-current" />
                </span>
                <p className="font-display text-7xl font-semibold text-gold">
                    {status}
                </p>
                <h1 className="mt-4 font-display text-3xl">{message.title}</h1>
                <p className="mt-3 max-w-md text-sm text-ivory/75">
                    {message.description}
                </p>
                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    <Button asChild variant="gold">
                        <Link href={home()}>Return to {hotel.name}</Link>
                    </Button>
                    {auth.user && (
                        <Button
                            asChild
                            variant="outline"
                            className="border-ivory/30 bg-transparent text-ivory hover:bg-white/10 hover:text-white"
                        >
                            <Link href={dashboard()}>Go to my dashboard</Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}
