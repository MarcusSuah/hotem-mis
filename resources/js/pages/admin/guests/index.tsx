import { Head, Link } from '@inertiajs/react';
import { Plus, Search, Star, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { GuestFormDialog } from '@/components/admin/guests/guest-form-dialog';
import type { EditableGuest } from '@/components/admin/guests/guest-form-dialog';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type GuestRow = EditableGuest & {
    name: string;
    initials: string;
    stays_count: number;
    last_stay: string | null;
};

export default function GuestsIndex({
    guests,
    filters: initialFilters,
    currencies,
    can,
}: {
    guests: Paginated<GuestRow>;
    filters: { search: string; segment: string };
    currencies: string[];
    can: { create: boolean; update: boolean; viewIdentity: boolean };
}) {
    const { filters, setFilter } = useFilters(
        admin.guests.index.url(),
        initialFilters,
    );
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Guests" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Guests"
                    description="Guest profiles, stay history and preferences."
                    actions={
                        can.create && (
                            <Button onClick={() => setCreating(true)}>
                                <Plus />
                                New guest
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <Tabs
                        value={filters.segment || 'all'}
                        onValueChange={(value) =>
                            setFilter(
                                'segment',
                                value === 'all' ? '' : value,
                                true,
                            )
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="in_house">In house</TabsTrigger>
                            <TabsTrigger value="vip">VIP</TabsTrigger>
                            <TabsTrigger value="repeat">Repeat</TabsTrigger>
                            <TabsTrigger value="marketing">
                                Marketing opt-in
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <div className="relative md:w-80">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            aria-label="Search guests"
                            placeholder="Name, email or phone"
                            className="pl-9"
                            value={filters.search}
                            onChange={(event) =>
                                setFilter('search', event.target.value)
                            }
                        />
                    </div>
                </div>

                <div className="rounded-xl border bg-card shadow-xs">
                    {guests.data.length === 0 ? (
                        <EmptyState icon={UsersRound} title="No guests found" />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Guest</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Contact
                                    </TableHead>
                                    <TableHead>Stays</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Last stay
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {guests.data.map((guest) => (
                                    <TableRow key={guest.id}>
                                        <TableCell>
                                            <Link
                                                href={admin.guests.show(
                                                    guest.id,
                                                )}
                                                className="flex items-center gap-3"
                                            >
                                                <span className="flex size-9 items-center justify-center rounded-full bg-secondary text-sm font-semibold text-primary dark:text-foreground">
                                                    {guest.initials}
                                                </span>
                                                <span>
                                                    <span className="flex items-center gap-1 font-medium hover:underline">
                                                        {guest.name}
                                                        {guest.is_vip && (
                                                            <Star
                                                                className="size-3.5 fill-highlight text-highlight"
                                                                aria-label="VIP"
                                                            />
                                                        )}
                                                    </span>
                                                    {guest.country_code && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {guest.country_code}
                                                        </span>
                                                    )}
                                                </span>
                                            </Link>
                                        </TableCell>
                                        <TableCell className="hidden text-sm md:table-cell">
                                            {guest.email ?? '—'}
                                            <span className="block text-xs text-muted-foreground">
                                                {guest.phone}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {guest.stays_count}{' '}
                                            {guest.stays_count >= 2 && (
                                                <Badge variant="gold">
                                                    Repeat
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {formatDate(guest.last_stay)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={guests} />
                    </div>
                </div>
            </div>
            <GuestFormDialog
                open={creating}
                onOpenChange={setCreating}
                guest={null}
                currencies={currencies}
                canRecordIdentity={can.viewIdentity}
            />
        </>
    );
}

GuestsIndex.layout = {
    breadcrumbs: [{ title: 'Guests', href: admin.guests.index() }],
};
