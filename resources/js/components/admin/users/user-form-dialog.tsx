import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
    is_active: boolean;
    is_protected: boolean;
    roles: string[];
    last_login_at: string | null;
    created_at: string | null;
    can: { update: boolean; delete: boolean; deactivate: boolean };
};

type UserFormData = {
    name: string;
    email: string;
    phone: string;
    password: string;
    password_confirmation: string;
    roles: string[];
};

const blank: UserFormData = {
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    roles: [],
};

export function UserFormDialog({
    open,
    onOpenChange,
    user,
    roles,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: ManagedUser | null;
    roles: string[];
}) {
    const form = useForm<UserFormData>(blank);
    const isEditing = user !== null;
    const errors = form.errors as Record<string, string | undefined>;
    const roleError =
        errors.roles ??
        Object.entries(errors).find(([key]) => key.startsWith('roles.'))?.[1];

    useEffect(() => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.setData(
            user
                ? {
                      ...blank,
                      name: user.name,
                      email: user.email,
                      phone: user.phone ?? '',
                      roles: user.roles,
                  }
                : blank,
        );
        // Re-initialise only when the dialog opens for a different record.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user?.id]);

    function toggleRole(role: string, checked: boolean) {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, role]
                : form.data.roles.filter((value) => value !== role),
        );
    }

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
            onError: notifyValidationFailed,
        };

        if (user) {
            form.put(admin.users.update.url(user.id), options);
        } else {
            form.post(admin.users.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={user ? `Edit ${user.name}` : 'New user'}
            description={
                isEditing
                    ? 'Update account details and role assignments.'
                    : 'Create a staff or guest account. The user can change their password after signing in.'
            }
            submitLabel={isEditing ? 'Save changes' : 'Create user'}
            processing={form.processing}
            onSubmit={submit}
            size="lg"
        >
            <FormErrorSummary errors={form.errors} />

            <div className="grid gap-5 sm:grid-cols-2">
                <Field
                    label="Full name"
                    htmlFor="user-name"
                    error={form.errors.name}
                    required
                >
                    <Input
                        id="user-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        autoComplete="off"
                        aria-invalid={Boolean(form.errors.name)}
                        required
                    />
                </Field>
                <Field
                    label="Email"
                    htmlFor="user-email"
                    error={form.errors.email}
                    required
                >
                    <Input
                        id="user-email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        autoComplete="off"
                        aria-invalid={Boolean(form.errors.email)}
                        required
                    />
                </Field>
                <Field
                    label="Phone"
                    htmlFor="user-phone"
                    error={form.errors.phone}
                >
                    <Input
                        id="user-phone"
                        type="tel"
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.phone)}
                    />
                </Field>
                <div className="hidden sm:block" />
                <Field
                    label={isEditing ? 'New password' : 'Password'}
                    htmlFor="user-password"
                    error={form.errors.password}
                    hint={
                        isEditing
                            ? 'Leave blank to keep the current password.'
                            : undefined
                    }
                    required={!isEditing}
                >
                    <Input
                        id="user-password"
                        type="password"
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        autoComplete="new-password"
                        aria-invalid={Boolean(form.errors.password)}
                    />
                </Field>
                <Field
                    label="Confirm password"
                    htmlFor="user-password-confirmation"
                    error={form.errors.password_confirmation}
                >
                    <Input
                        id="user-password-confirmation"
                        type="password"
                        value={form.data.password_confirmation}
                        onChange={(event) =>
                            form.setData(
                                'password_confirmation',
                                event.target.value,
                            )
                        }
                        autoComplete="new-password"
                    />
                </Field>
            </div>

            <fieldset className="space-y-3">
                <legend className="text-sm font-medium">
                    Roles <span className="text-destructive">*</span>
                </legend>
                {user?.is_protected && (
                    <p className="text-xs text-muted-foreground">
                        The protected Super Admin always keeps the Super Admin
                        role.
                    </p>
                )}
                <div className="grid gap-2 sm:grid-cols-2">
                    {roles.map((role) => {
                        const id = `role-${role.replace(/\s+/g, '-').toLowerCase()}`;
                        const locked =
                            user?.is_protected && role === 'Super Admin';

                        return (
                            <label
                                key={role}
                                htmlFor={id}
                                className="flex cursor-pointer items-center gap-3 rounded-md border p-3 text-sm has-data-[state=checked]:border-primary/50 has-data-[state=checked]:bg-secondary/60"
                            >
                                <Checkbox
                                    id={id}
                                    checked={form.data.roles.includes(role)}
                                    disabled={locked}
                                    onCheckedChange={(checked) =>
                                        toggleRole(role, checked === true)
                                    }
                                />
                                {role}
                            </label>
                        );
                    })}
                </div>
                <InputError message={roleError} />
            </fieldset>
        </FormDialog>
    );
}
