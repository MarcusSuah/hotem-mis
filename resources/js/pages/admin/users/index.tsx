import { Head, router } from '@inertiajs/react';
import {
    MoreHorizontal,
    Pencil,
    Plus,
    Power,
    PowerOff,
    Search,
    ShieldCheck,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { UserFormDialog } from '@/components/admin/users/user-form-dialog';
import type { ManagedUser } from '@/components/admin/users/user-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { UserInfo } from '@/components/user-info';
import { useFilters } from '@/hooks/use-filters';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Filters = { search: string; role: string; status: string };

type PendingAction =
    | { type: 'delete'; user: ManagedUser }
    | { type: 'deactivate'; user: ManagedUser }
    | { type: 'bulk-delete' };

export default function UsersIndex({
    users,
    filters: initialFilters,
    roles,
    can,
}: {
    users: Paginated<ManagedUser>;
    filters: Filters;
    roles: string[];
    can: { create: boolean; bulkDelete: boolean };
}) {
    const { filters, setFilter, reset } = useFilters<Filters>(
        admin.users.index.url(),
        initialFilters,
    );
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [processing, setProcessing] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);

    const selectable = users.data.filter((user) => user.can.delete);
    const allSelected =
        selectable.length > 0 &&
        selectable.every((user) => selected.includes(user.id));

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(user: ManagedUser) {
        setEditing(user);
        setFormOpen(true);
    }

    function toggleAll(checked: boolean) {
        setSelected(checked ? selectable.map((user) => user.id) : []);
    }

    function toggleOne(id: number, checked: boolean) {
        setSelected((current) =>
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );
    }

    function confirmPending() {
        if (!pending) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        };

        if (pending.type === 'delete') {
            router.delete(admin.users.destroy.url(pending.user.id), options);
        } else if (pending.type === 'deactivate') {
            router.delete(admin.users.deactivate.url(pending.user.id), options);
        } else {
            router.post(
                admin.users.bulkDelete.url(),
                { ids: selected },
                { ...options, onSuccess: () => setSelected([]) },
            );
        }
    }

    function activate(user: ManagedUser) {
        router.post(
            admin.users.activate.url(user.id),
            {},
            { preserveScroll: true },
        );
    }

    const hasFilters = Object.values(filters).some((value) => value !== '');

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Users"
                    description="Manage staff and guest accounts, their roles and access."
                    actions={
                        <>
                            {can.bulkDelete && selected.length > 0 && (
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    onClick={() =>
                                        setPending({ type: 'bulk-delete' })
                                    }
                                >
                                    <Trash2 />
                                    Delete {selected.length} selected
                                </Button>
                            )}
                            {can.create && (
                                <Button onClick={openCreate}>
                                    <Plus />
                                    New user
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center">
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search users"
                                placeholder="Search name, email or phone"
                                className="pl-9"
                                value={filters.search}
                                onChange={(event) =>
                                    setFilter('search', event.target.value)
                                }
                            />
                        </div>
                        <Select
                            value={filters.role || 'all'}
                            onValueChange={(value) =>
                                setFilter(
                                    'role',
                                    value === 'all' ? '' : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger
                                className="md:w-52"
                                aria-label="Filter by role"
                            >
                                <SelectValue placeholder="All roles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All roles</SelectItem>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {role}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                setFilter(
                                    'status',
                                    value === 'all' ? '' : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger
                                className="md:w-40"
                                aria-label="Filter by status"
                            >
                                <SelectValue placeholder="Any status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Deactivated
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        {hasFilters && (
                            <Button variant="ghost" onClick={reset}>
                                Clear
                            </Button>
                        )}
                    </div>

                    {users.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No users found"
                            description={
                                hasFilters
                                    ? 'Try adjusting your search or filters.'
                                    : 'Create the first account to get started.'
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {can.bulkDelete && (
                                        <TableHead className="w-10">
                                            <Checkbox
                                                aria-label="Select all deletable users"
                                                checked={allSelected}
                                                disabled={
                                                    selectable.length === 0
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleAll(checked === true)
                                                }
                                            />
                                        </TableHead>
                                    )}
                                    <TableHead>User</TableHead>
                                    <TableHead>Roles</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Last sign-in
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow
                                        key={user.id}
                                        data-state={
                                            selected.includes(user.id)
                                                ? 'selected'
                                                : undefined
                                        }
                                    >
                                        {can.bulkDelete && (
                                            <TableCell>
                                                <Checkbox
                                                    aria-label={`Select ${user.name}`}
                                                    disabled={!user.can.delete}
                                                    checked={selected.includes(
                                                        user.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleOne(
                                                            user.id,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <UserInfo
                                                    user={{
                                                        ...user,
                                                        avatar: user.avatar_url,
                                                        email_verified_at: null,
                                                        created_at:
                                                            user.created_at ??
                                                            '',
                                                        updated_at: '',
                                                    }}
                                                    showEmail
                                                />
                                                {user.is_protected && (
                                                    <Badge
                                                        variant="gold"
                                                        title="Protected Super Admin"
                                                    >
                                                        <ShieldCheck />
                                                        Protected
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.map((role) => (
                                                    <Badge
                                                        key={role}
                                                        variant="secondary"
                                                    >
                                                        {role}
                                                    </Badge>
                                                ))}
                                                {user.roles.length === 0 && (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    user.is_active
                                                        ? 'success'
                                                        : 'muted'
                                                }
                                            >
                                                {user.is_active
                                                    ? 'Active'
                                                    : 'Deactivated'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground lg:table-cell">
                                            {formatDateTime(user.last_login_at)}
                                        </TableCell>
                                        <TableCell>
                                            <UserActions
                                                user={user}
                                                onEdit={() => openEdit(user)}
                                                onActivate={() =>
                                                    activate(user)
                                                }
                                                onDeactivate={() =>
                                                    setPending({
                                                        type: 'deactivate',
                                                        user,
                                                    })
                                                }
                                                onDelete={() =>
                                                    setPending({
                                                        type: 'delete',
                                                        user,
                                                    })
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    <div className="border-t p-4">
                        <Pagination paginator={users} />
                    </div>
                </div>
            </div>

            <UserFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                user={editing}
                roles={roles}
            />

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                processing={processing}
                destructive
                onConfirm={confirmPending}
                title={
                    pending?.type === 'deactivate'
                        ? 'Deactivate account?'
                        : pending?.type === 'bulk-delete'
                          ? `Delete ${selected.length} users?`
                          : 'Delete user?'
                }
                confirmLabel={
                    pending?.type === 'deactivate' ? 'Deactivate' : 'Delete'
                }
                description={
                    pending?.type === 'deactivate'
                        ? `${pending.user.name} will be signed out and cannot sign in until reactivated.`
                        : pending?.type === 'delete'
                          ? `${pending.user.name} will lose access immediately. Historical records they created are preserved.`
                          : 'Selected accounts will lose access immediately. Protected and restricted accounts are skipped automatically.'
                }
            />
        </>
    );
}

function UserActions({
    user,
    onEdit,
    onActivate,
    onDeactivate,
    onDelete,
}: {
    user: ManagedUser;
    onEdit: () => void;
    onActivate: () => void;
    onDeactivate: () => void;
    onDelete: () => void;
}) {
    const hasActions =
        user.can.update || user.can.delete || user.can.deactivate;

    if (!hasActions) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Actions for ${user.name}`}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {user.can.update && (
                    <DropdownMenuItem onSelect={onEdit}>
                        <Pencil />
                        Edit
                    </DropdownMenuItem>
                )}
                {user.can.update && !user.is_active && (
                    <DropdownMenuItem onSelect={onActivate}>
                        <Power />
                        Activate
                    </DropdownMenuItem>
                )}
                {user.can.deactivate && user.is_active && (
                    <DropdownMenuItem onSelect={onDeactivate}>
                        <PowerOff />
                        Deactivate
                    </DropdownMenuItem>
                )}
                {user.can.delete && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={onDelete}
                        >
                            <Trash2 />
                            Delete
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: admin.users.index() }],
};
