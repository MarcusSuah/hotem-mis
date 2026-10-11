import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';

export type EditableGuest = {
    id: number;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    country_code: string | null;
    address?: string | null;
    date_of_birth?: string | null;
    is_vip: boolean;
    marketing_consent: boolean;
    preferred_currency?: string | null;
    notes?: string | null;
    identity_type?: string | null;
};

const blank = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    country_code: '',
    address: '',
    date_of_birth: '',
    identity_type: '',
    identity_number: '',
    is_vip: false,
    marketing_consent: false,
    preferred_currency: '',
    notes: '',
};

export function GuestFormDialog({
    open,
    onOpenChange,
    guest,
    currencies,
    canRecordIdentity,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    guest: EditableGuest | null;
    currencies: string[];
    canRecordIdentity: boolean;
}) {
    const form = useForm(blank);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.setData(
            guest
                ? {
                      ...blank,
                      first_name: guest.first_name,
                      last_name: guest.last_name,
                      email: guest.email ?? '',
                      phone: guest.phone ?? '',
                      country_code: guest.country_code ?? '',
                      address: guest.address ?? '',
                      date_of_birth: guest.date_of_birth ?? '',
                      identity_type: guest.identity_type ?? '',
                      is_vip: guest.is_vip,
                      marketing_consent: guest.marketing_consent,
                      preferred_currency: guest.preferred_currency ?? '',
                      notes: guest.notes ?? '',
                  }
                : blank,
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, guest?.id]);

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: notifyValidationFailed,
        };

        if (guest) {
            form.put(admin.guests.update.url(guest.id), options);
        } else {
            form.post(admin.guests.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                guest
                    ? `Edit ${guest.first_name} ${guest.last_name}`
                    : 'New guest'
            }
            submitLabel={guest ? 'Save changes' : 'Create guest'}
            processing={form.processing}
            onSubmit={submit}
            size="lg"
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="First name"
                    htmlFor="g-first"
                    error={errors.first_name}
                    required
                >
                    <Input
                        id="g-first"
                        value={data.first_name}
                        onChange={(event) =>
                            setData('first_name', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Last name"
                    htmlFor="g-last"
                    error={errors.last_name}
                    required
                >
                    <Input
                        id="g-last"
                        value={data.last_name}
                        onChange={(event) =>
                            setData('last_name', event.target.value)
                        }
                    />
                </Field>
                <Field label="Email" htmlFor="g-email" error={errors.email}>
                    <Input
                        id="g-email"
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                    />
                </Field>
                <Field label="Phone" htmlFor="g-phone" error={errors.phone}>
                    <Input
                        id="g-phone"
                        type="tel"
                        value={data.phone}
                        onChange={(event) =>
                            setData('phone', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Country (ISO)"
                    htmlFor="g-country"
                    error={errors.country_code}
                >
                    <Input
                        id="g-country"
                        maxLength={2}
                        className="uppercase"
                        value={data.country_code}
                        onChange={(event) =>
                            setData('country_code', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Date of birth"
                    htmlFor="g-dob"
                    error={errors.date_of_birth}
                >
                    <Input
                        id="g-dob"
                        type="date"
                        value={data.date_of_birth}
                        onChange={(event) =>
                            setData('date_of_birth', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Address"
                    htmlFor="g-address"
                    error={errors.address}
                    className="sm:col-span-2"
                >
                    <Input
                        id="g-address"
                        value={data.address}
                        onChange={(event) =>
                            setData('address', event.target.value)
                        }
                    />
                </Field>
                {canRecordIdentity && (
                    <>
                        <Field
                            label="ID document"
                            htmlFor="g-id-type"
                            error={errors.identity_type}
                        >
                            <Select
                                value={data.identity_type}
                                onValueChange={(value) =>
                                    setData('identity_type', value)
                                }
                            >
                                <SelectTrigger id="g-id-type">
                                    <SelectValue placeholder="Not recorded" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="passport">
                                        Passport
                                    </SelectItem>
                                    <SelectItem value="national_id">
                                        National ID
                                    </SelectItem>
                                    <SelectItem value="drivers_license">
                                        Driver's licence
                                    </SelectItem>
                                    <SelectItem value="other">Other</SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Document number"
                            htmlFor="g-id-number"
                            error={errors.identity_number}
                            hint="Leave blank to keep the number on file."
                        >
                            <Input
                                id="g-id-number"
                                autoComplete="off"
                                value={data.identity_number}
                                onChange={(event) =>
                                    setData(
                                        'identity_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </>
                )}
                <Field
                    label="Preferred currency"
                    htmlFor="g-currency"
                    error={errors.preferred_currency}
                >
                    <Select
                        value={data.preferred_currency || 'none'}
                        onValueChange={(value) =>
                            setData(
                                'preferred_currency',
                                value === 'none' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger id="g-currency">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">No preference</SelectItem>
                            {currencies.map((currency) => (
                                <SelectItem key={currency} value={currency}>
                                    {currency}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <div className="flex flex-col justify-end gap-3">
                    <div className="flex items-center gap-2">
                        <Switch
                            id="g-vip"
                            checked={data.is_vip}
                            onCheckedChange={(checked) =>
                                setData('is_vip', checked)
                            }
                        />
                        <Label htmlFor="g-vip">VIP guest</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            id="g-marketing"
                            checked={data.marketing_consent}
                            onCheckedChange={(checked) =>
                                setData('marketing_consent', checked)
                            }
                        />
                        <Label htmlFor="g-marketing">Agreed to marketing</Label>
                    </div>
                </div>
                <Field
                    label="Preferences & notes"
                    htmlFor="g-notes"
                    error={errors.notes}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="g-notes"
                        rows={3}
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Prefers high floor, allergic to feathers…"
                    />
                </Field>
            </div>
        </FormDialog>
    );
}
