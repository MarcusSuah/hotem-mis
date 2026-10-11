import { Head, router, useForm } from '@inertiajs/react';
import {
    KeyRound,
    Lock,
    Pencil,
    Plus,
    ShieldCheck,
    Trash2,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';

type ManagedRole = {
    id: number;
    name: string;
    is_system: boolean;
    is_super_admin: boolean;
    users_count: number;
    permissions: string[];
    can: { update: boolean; delete: boolean };
};

type PermissionGroups = Record<string, string[]>;

export default function RolesIndex({
    roles,
    permissionGroups,
    can,
}: {
    roles: ManagedRole[];
    permissionGroups: PermissionGroups;
    can: { create: boolean; createPermission: boolean };
}) {
    const [editing, setEditing] = useState<ManagedRole | null>(null);
    const [roleFormOpen, setRoleFormOpen] = useState(false);
    const [permissionFormOpen, setPermissionFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<ManagedRole | null>(null);
    const [processing, setProcessing] = useState(false);

    const totalPermissions = Object.values(permissionGroups).flat().length;

    function deleteRole() {
        if (!deleting) {
            return;
        }

        router.delete(admin.roles.destroy.url(deleting.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDeleting(null);
            },
        });
    }

    return (
        <>
            <Head title="Roles & permissions" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Roles & permissions"
                    description="Roles bundle granular permissions. Staff can only grant permissions they hold themselves."
                    actions={
                        <>
                            {can.createPermission && (
                                <Button
                                    variant="outline"
                                    onClick={() => setPermissionFormOpen(true)}
                                >
                                    <KeyRound />
                                    New permission
                                </Button>
                            )}
                            {can.create && (
                                <Button
                                    onClick={() => {
                                        setEditing(null);
                                        setRoleFormOpen(true);
                                    }}
                                >
                                    <Plus />
                                    New role
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {roles.map((role) => (
                        <article
                            key={role.id}
                            className="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <h2 className="flex items-center gap-2 font-display text-xl font-semibold">
                                        {role.is_super_admin && (
                                            <ShieldCheck className="size-5 text-highlight" />
                                        )}
                                        {role.name}
                                    </h2>
                                    <div className="flex flex-wrap gap-1">
                                        {role.is_system && (
                                            <Badge variant="muted">
                                                Built-in
                                            </Badge>
                                        )}
                                        {role.is_super_admin && (
                                            <Badge variant="gold">
                                                <Lock />
                                                Locked
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                <div className="flex gap-1">
                                    {role.can.update && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Edit ${role.name}`}
                                            onClick={() => {
                                                setEditing(role);
                                                setRoleFormOpen(true);
                                            }}
                                        >
                                            <Pencil />
                                        </Button>
                                    )}
                                    {role.can.delete && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="text-destructive"
                                            aria-label={`Delete ${role.name}`}
                                            onClick={() => setDeleting(role)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div className="rounded-lg bg-muted/60 p-3">
                                    <dt className="text-muted-foreground">
                                        Permissions
                                    </dt>
                                    <dd className="mt-1 font-display text-2xl font-semibold">
                                        {role.permissions.length}
                                        <span className="text-sm font-normal text-muted-foreground">
                                            {' '}
                                            / {totalPermissions}
                                        </span>
                                    </dd>
                                </div>
                                <div className="rounded-lg bg-muted/60 p-3">
                                    <dt className="flex items-center gap-1 text-muted-foreground">
                                        <Users className="size-3.5" /> Users
                                    </dt>
                                    <dd className="mt-1 font-display text-2xl font-semibold">
                                        {role.users_count}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    ))}
                </div>
            </div>

            <RoleFormDialog
                open={roleFormOpen}
                onOpenChange={setRoleFormOpen}
                role={editing}
                permissionGroups={permissionGroups}
            />

            <PermissionFormDialog
                open={permissionFormOpen}
                onOpenChange={setPermissionFormOpen}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete the ${deleting?.name} role?`}
                description="Users must be reassigned before a role can be deleted. This cannot be undone."
                confirmLabel="Delete role"
                destructive
                processing={processing}
                onConfirm={deleteRole}
            />
        </>
    );
}

function RoleFormDialog({
    open,
    onOpenChange,
    role,
    permissionGroups,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    role: ManagedRole | null;
    permissionGroups: PermissionGroups;
}) {
    const form = useForm<{ name: string; permissions: string[] }>({
        name: '',
        permissions: [],
    });
    const errors = form.errors as Record<string, string | undefined>;

    useEffect(() => {
        if (open) {
            form.clearErrors();
            form.setData({
                name: role?.name ?? '',
                permissions: role?.permissions ?? [],
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, role?.id]);

    function toggle(permission: string, checked: boolean) {
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, permission]
                : form.data.permissions.filter((value) => value !== permission),
        );
    }

    function toggleGroup(permissions: string[], checked: boolean) {
        const others = form.data.permissions.filter(
            (value) => !permissions.includes(value),
        );
        form.setData(
            'permissions',
            checked ? [...others, ...permissions] : others,
        );
    }

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: notifyValidationFailed,
        };

        if (role) {
            form.put(admin.roles.update.url(role.id), options);
        } else {
            form.post(admin.roles.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={role ? `Edit ${role.name}` : 'New role'}
            description="Choose the permissions this role grants."
            submitLabel={role ? 'Save role' : 'Create role'}
            processing={form.processing}
            onSubmit={submit}
            size="xl"
            footerStart={`${form.data.permissions.length} selected`}
        >
            <FormErrorSummary errors={form.errors} />

            <Field
                label="Role name"
                htmlFor="role-name"
                error={form.errors.name}
                required
            >
                <Input
                    id="role-name"
                    value={form.data.name}
                    disabled={role?.is_system}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.name)}
                />
            </Field>

            <InputError
                message={
                    errors.permissions ??
                    Object.entries(errors).find(([key]) =>
                        key.startsWith('permissions.'),
                    )?.[1]
                }
            />

            <div className="grid gap-4 md:grid-cols-2">
                {Object.entries(permissionGroups).map(
                    ([group, permissions]) => {
                        const checkedCount = permissions.filter((permission) =>
                            form.data.permissions.includes(permission),
                        ).length;
                        const groupId = `group-${group.replace(/\W+/g, '-').toLowerCase()}`;

                        return (
                            <fieldset
                                key={group}
                                className="rounded-lg border p-4"
                            >
                                <legend className="sr-only">{group}</legend>
                                <label
                                    htmlFor={groupId}
                                    className="flex items-center gap-2 border-b pb-3 text-sm font-semibold"
                                >
                                    <Checkbox
                                        id={groupId}
                                        checked={
                                            checkedCount === permissions.length
                                                ? true
                                                : checkedCount > 0
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={(checked) =>
                                            toggleGroup(
                                                permissions,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {group}
                                    <span className="ml-auto text-xs font-normal text-muted-foreground">
                                        {checkedCount}/{permissions.length}
                                    </span>
                                </label>
                                <div className="mt-3 grid gap-2">
                                    {permissions.map((permission) => {
                                        const id = `permission-${permission}`;

                                        return (
                                            <label
                                                key={permission}
                                                htmlFor={id}
                                                className="flex items-center gap-2 font-mono text-xs"
                                            >
                                                <Checkbox
                                                    id={id}
                                                    checked={form.data.permissions.includes(
                                                        permission,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggle(
                                                            permission,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                {permission}
                                            </label>
                                        );
                                    })}
                                </div>
                            </fieldset>
                        );
                    },
                )}
            </div>
        </FormDialog>
    );
}

function PermissionFormDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ name: '' });

    function submit() {
        form.post(admin.permissions.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
            onError: notifyValidationFailed,
        });
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="New permission"
            description="Custom permissions are granted to the Super Admin role automatically and can then be added to other roles."
            submitLabel="Create permission"
            processing={form.processing}
            onSubmit={submit}
        >
            <Field
                label="Permission name"
                htmlFor="permission-name"
                error={form.errors.name}
                hint='Format: "area.action", e.g. "spa.manage".'
                required
            >
                <Input
                    id="permission-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="spa.manage"
                    className="font-mono"
                    aria-invalid={Boolean(form.errors.name)}
                />
            </Field>
        </FormDialog>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles & permissions', href: admin.roles.index() }],
};
