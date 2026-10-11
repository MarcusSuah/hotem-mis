import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Mail, Phone, UserRound } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { edit } from '@/routes/profile';

type Profile = {
    name: string;
    email: string;
    phone: string | null;
    member_since: string | null;
};

export default function AccountDashboard({ profile }: { profile: Profile }) {
    return (
        <>
            <Head title="My account" />
            <section className="bg-sage/60 dark:bg-muted/40">
                <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6">
                    <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                        My account
                    </p>
                    <h1 className="mt-2 font-display text-4xl font-semibold text-primary dark:text-foreground">
                        Welcome, {profile.name}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Manage your profile and stay details in one place.
                    </p>
                </div>
            </section>

            <div className="mx-auto grid max-w-5xl gap-6 px-4 py-10 sm:px-6 md:grid-cols-3">
                <div className="rounded-xl border bg-card p-6 shadow-xs md:col-span-2">
                    <h2 className="font-display text-2xl text-primary dark:text-foreground">
                        Profile
                    </h2>
                    <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div className="flex items-center gap-3">
                            <UserRound className="size-4 text-highlight" />
                            <div>
                                <dt className="text-muted-foreground">Name</dt>
                                <dd className="font-medium">{profile.name}</dd>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <Mail className="size-4 text-highlight" />
                            <div>
                                <dt className="text-muted-foreground">Email</dt>
                                <dd className="font-medium break-all">
                                    {profile.email}
                                </dd>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <Phone className="size-4 text-highlight" />
                            <div>
                                <dt className="text-muted-foreground">Phone</dt>
                                <dd className="font-medium">
                                    {profile.phone ?? 'Not provided'}
                                </dd>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <CalendarDays className="size-4 text-highlight" />
                            <div>
                                <dt className="text-muted-foreground">
                                    Member since
                                </dt>
                                <dd className="font-medium">
                                    {formatDate(profile.member_since)}
                                </dd>
                            </div>
                        </div>
                    </dl>
                    <Button asChild variant="outline" className="mt-6">
                        <Link href={edit()}>Edit profile</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
