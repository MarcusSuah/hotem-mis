import { Head, usePage } from '@inertiajs/react';
import { UserCheck, UserX, Users } from 'lucide-react';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import admin from '@/routes/admin';

type Stats = {
    staff: number;
    guestAccounts: number;
    inactiveAccounts: number;
};

export default function AdminDashboard({ stats }: { stats: Stats }) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Welcome back, ${auth.user.name.split(' ')[0]}`}
                    description="An overview of your hotel operations."
                />
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard
                        label="Staff accounts"
                        value={stats.staff}
                        icon={Users}
                    />
                    <StatCard
                        label="Guest accounts"
                        value={stats.guestAccounts}
                        icon={UserCheck}
                    />
                    <StatCard
                        label="Deactivated accounts"
                        value={stats.inactiveAccounts}
                        icon={UserX}
                    />
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: admin.dashboard() }],
};
