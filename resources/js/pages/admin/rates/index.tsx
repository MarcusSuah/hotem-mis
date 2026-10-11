import type { FormDataType } from '@inertiajs/core';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';
import { Tags } from 'lucide-react';

type RatePlan = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    cancellation_policy_id: number;
    cancellation_policy: string;
    is_refundable: boolean;
    adjustment_percent: string;
    includes_breakfast: boolean;
    min_nights: number;
    deposit_percent: string | null;
    is_public: boolean;
    is_active: boolean;
    is_default: boolean;
    display_order: number;
};

type Season = {
    id: number;
    room_type_id: number;
    room_type: string;
    name: string;
    start_date: string;
    end_date: string;
    rate: MoneyValue;
    weekend_rate: MoneyValue | null;
    priority: number;
    is_active: boolean;
};

type Policy = {
    id: number;
    name: string;
    description: string | null;
    free_cancellation_hours: number;
    penalty_type: string;
    penalty_percent: string;
    is_default: boolean;
    summary: string;
};

type Amenity = {
    id: number;
    slug: string;
    name: string;
    icon: string | null;
    category: string;
    is_active: boolean;
    display_order: number;
    room_types_count: number;
};

type Option = { value: string; label: string };

type Props = {
    baseCurrency: string;
    ratePlans: RatePlan[];
    seasonalRates: Season[];
    cancellationPolicies: Policy[];
    amenities: Amenity[];
    roomTypes: { id: number; name: string }[];
    penaltyTypes: Option[];
    can: { manageRates: boolean; manageAmenities: boolean };
};

type Deletion = { label: string; url: string } | null;

export default function RatesIndex(props: Props) {
    const { can } = props;
    const [deletion, setDeletion] = useState<Deletion>(null);
    const [processing, setProcessing] = useState(false);
    const [plan, setPlan] = useState<RatePlan | null | undefined>(undefined);
    const [season, setSeason] = useState<Season | null | undefined>(undefined);
    const [policy, setPolicy] = useState<Policy | null | undefined>(undefined);
    const [amenity, setAmenity] = useState<Amenity | null | undefined>(
        undefined,
    );

    function confirmDeletion() {
        if (!deletion) {
            return;
        }

        router.delete(deletion.url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDeletion(null);
            },
        });
    }

    const rowActions = (
        editable: boolean,
        onEdit: () => void,
        label: string,
        url: string,
    ) =>
        editable && (
            <div className="flex justify-end gap-1">
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Edit ${label}`}
                    onClick={onEdit}
                >
                    <Pencil />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-destructive"
                    aria-label={`Delete ${label}`}
                    onClick={() => setDeletion({ label, url })}
                >
                    <Trash2 />
                </Button>
            </div>
        );

    return (
        <>
            <Head title="Rates & policies" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Rates & policies"
                    description={`Rate plans, seasonal pricing, cancellation terms and amenities. Amounts are in ${props.baseCurrency}, the base currency. Changes apply to new bookings only.`}
                />

                <Tabs defaultValue="plans" className="gap-4">
                    <TabsList className="flex-wrap">
                        <TabsTrigger value="plans">Rate plans</TabsTrigger>
                        <TabsTrigger value="seasons">
                            Seasonal rates
                        </TabsTrigger>
                        <TabsTrigger value="policies">
                            Cancellation policies
                        </TabsTrigger>
                        <TabsTrigger value="amenities">Amenities</TabsTrigger>
                    </TabsList>

                    <TabsContent value="plans">
                        <Panel
                            title="Rate plans"
                            onCreate={
                                can.manageRates
                                    ? () => setPlan(null)
                                    : undefined
                            }
                            empty={props.ratePlans.length === 0}
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Adjustment</TableHead>
                                        <TableHead>Policy</TableHead>
                                        <TableHead>Min stay</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {props.ratePlans.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell>
                                                <span className="font-medium">
                                                    {item.name}
                                                </span>
                                                <span className="block font-mono text-xs text-muted-foreground">
                                                    {item.code}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {Number(
                                                    item.adjustment_percent,
                                                ) === 0
                                                    ? 'Standard'
                                                    : `${Number(item.adjustment_percent) > 0 ? '+' : ''}${Number(item.adjustment_percent)}%`}
                                            </TableCell>
                                            <TableCell>
                                                {item.cancellation_policy}
                                            </TableCell>
                                            <TableCell>
                                                {item.min_nights} night
                                                {item.min_nights > 1 ? 's' : ''}
                                            </TableCell>
                                            <TableCell className="space-x-1">
                                                {item.is_default && (
                                                    <Badge variant="gold">
                                                        Default
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant={
                                                        item.is_active
                                                            ? 'success'
                                                            : 'muted'
                                                    }
                                                >
                                                    {item.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                                {!item.is_refundable && (
                                                    <Badge variant="warning">
                                                        Non-refundable
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {rowActions(
                                                    can.manageRates,
                                                    () => setPlan(item),
                                                    item.name,
                                                    admin.ratePlans.destroy.url(
                                                        item.id,
                                                    ),
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Panel>
                    </TabsContent>

                    <TabsContent value="seasons">
                        <Panel
                            title="Seasonal rates"
                            onCreate={
                                can.manageRates
                                    ? () => setSeason(null)
                                    : undefined
                            }
                            empty={props.seasonalRates.length === 0}
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Season</TableHead>
                                        <TableHead>Room type</TableHead>
                                        <TableHead>Dates</TableHead>
                                        <TableHead className="text-right">
                                            Nightly rate
                                        </TableHead>
                                        <TableHead>Priority</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {props.seasonalRates.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.name}{' '}
                                                {!item.is_active && (
                                                    <Badge variant="muted">
                                                        Inactive
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {item.room_type}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(item.start_date)} –{' '}
                                                {formatDate(item.end_date)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {item.rate.formatted}
                                                {item.weekend_rate && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        Fri/Sat{' '}
                                                        {
                                                            item.weekend_rate
                                                                .formatted
                                                        }
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {item.priority}
                                            </TableCell>
                                            <TableCell>
                                                {rowActions(
                                                    can.manageRates,
                                                    () => setSeason(item),
                                                    item.name,
                                                    admin.seasonalRates.destroy.url(
                                                        item.id,
                                                    ),
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Panel>
                    </TabsContent>

                    <TabsContent value="policies">
                        <Panel
                            title="Cancellation policies"
                            onCreate={
                                can.manageRates
                                    ? () => setPolicy(null)
                                    : undefined
                            }
                            empty={props.cancellationPolicies.length === 0}
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Policy</TableHead>
                                        <TableHead>Terms</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {props.cancellationPolicies.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.name}{' '}
                                                {item.is_default && (
                                                    <Badge variant="gold">
                                                        Default
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="whitespace-normal text-muted-foreground">
                                                {item.summary}
                                            </TableCell>
                                            <TableCell>
                                                {rowActions(
                                                    can.manageRates,
                                                    () => setPolicy(item),
                                                    item.name,
                                                    admin.cancellationPolicies.destroy.url(
                                                        item.id,
                                                    ),
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Panel>
                    </TabsContent>

                    <TabsContent value="amenities">
                        <Panel
                            title="Amenities"
                            onCreate={
                                can.manageAmenities
                                    ? () => setAmenity(null)
                                    : undefined
                            }
                            empty={props.amenities.length === 0}
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Amenity</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Used by</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {props.amenities.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.name}{' '}
                                                {!item.is_active && (
                                                    <Badge variant="muted">
                                                        Hidden
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {item.category}
                                            </TableCell>
                                            <TableCell>
                                                {item.room_types_count} room
                                                type
                                                {item.room_types_count === 1
                                                    ? ''
                                                    : 's'}
                                            </TableCell>
                                            <TableCell>
                                                {rowActions(
                                                    can.manageAmenities,
                                                    () => setAmenity(item),
                                                    item.name,
                                                    admin.amenities.destroy.url(
                                                        item.slug,
                                                    ),
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Panel>
                    </TabsContent>
                </Tabs>
            </div>

            <RatePlanDialog
                plan={plan}
                onClose={() => setPlan(undefined)}
                policies={props.cancellationPolicies}
            />
            <SeasonDialog
                season={season}
                onClose={() => setSeason(undefined)}
                roomTypes={props.roomTypes}
                currency={props.baseCurrency}
            />
            <PolicyDialog
                policy={policy}
                onClose={() => setPolicy(undefined)}
                penaltyTypes={props.penaltyTypes}
            />
            <AmenityDialog
                amenity={amenity}
                onClose={() => setAmenity(undefined)}
            />

            <ConfirmDialog
                open={deletion !== null}
                onOpenChange={(open) => !open && setDeletion(null)}
                title={`Delete ${deletion?.label}?`}
                description="Existing reservations keep the rates and terms they were booked with."
                confirmLabel="Delete"
                destructive
                processing={processing}
                onConfirm={confirmDeletion}
            />
        </>
    );
}

function Panel({
    title,
    onCreate,
    empty,
    children,
}: {
    title: string;
    onCreate?: () => void;
    empty: boolean;
    children: ReactNode;
}) {
    return (
        <div className="rounded-xl border bg-card shadow-xs">
            <div className="flex items-center justify-between border-b p-4">
                <h2 className="font-display text-xl font-semibold">{title}</h2>
                {onCreate && (
                    <Button size="sm" onClick={onCreate}>
                        <Plus />
                        Add
                    </Button>
                )}
            </div>
            {empty ? (
                <EmptyState
                    icon={Tags}
                    title={`No ${title.toLowerCase()} yet`}
                />
            ) : (
                children
            )}
        </div>
    );
}

function Toggle({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center gap-2">
            <Switch id={id} checked={checked} onCheckedChange={onChange} />
            <Label htmlFor={id}>{label}</Label>
        </div>
    );
}

/**
 * Opens when `record` is not undefined; `null` means create.
 */
function useRecordForm<T extends FormDataType<T>>(
    record: unknown,
    initial: T,
    fromRecord: () => T,
) {
    const form = useForm<T>(initial);

    useEffect(() => {
        if (record !== undefined) {
            form.clearErrors();
            form.setData(record === null ? initial : fromRecord());
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [record]);

    return form;
}

function save(
    form: ReturnType<typeof useForm>,
    isNew: boolean,
    storeUrl: string,
    updateUrl: string | null,
    onClose: () => void,
) {
    const options = {
        preserveScroll: true,
        onSuccess: onClose,
        onError: notifyValidationFailed,
    };

    if (isNew || updateUrl === null) {
        form.post(storeUrl, options);
    } else {
        form.put(updateUrl, options);
    }
}

function RatePlanDialog({
    plan,
    onClose,
    policies,
}: {
    plan: RatePlan | null | undefined;
    onClose: () => void;
    policies: Policy[];
}) {
    const initial = {
        name: '',
        code: '',
        description: '',
        cancellation_policy_id:
            policies.find((policy) => policy.is_default)?.id.toString() ?? '',
        is_refundable: true,
        adjustment_percent: '0',
        includes_breakfast: false,
        min_nights: '1',
        deposit_percent: '',
        is_public: true,
        is_active: true,
        is_default: false,
        display_order: '0',
    };
    const form = useRecordForm(plan, initial, () => ({
        ...initial,
        ...plan!,
        description: plan!.description ?? '',
        cancellation_policy_id: String(plan!.cancellation_policy_id),
        min_nights: String(plan!.min_nights),
        deposit_percent: plan!.deposit_percent ?? '',
        display_order: String(plan!.display_order),
    }));
    const { data, setData, errors } = form;

    return (
        <FormDialog
            open={plan !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={plan ? `Edit ${plan.name}` : 'New rate plan'}
            description="The adjustment is applied to every nightly room rate (e.g. -10 for a 10% discount)."
            processing={form.processing}
            onSubmit={() =>
                save(
                    form as never,
                    !plan,
                    admin.ratePlans.store.url(),
                    plan ? admin.ratePlans.update.url(plan.id) : null,
                    onClose,
                )
            }
            size="lg"
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Name"
                    htmlFor="plan-name"
                    error={errors.name}
                    required
                >
                    <Input
                        id="plan-name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Code"
                    htmlFor="plan-code"
                    error={errors.code}
                    required
                >
                    <Input
                        id="plan-code"
                        className="font-mono uppercase"
                        value={data.code}
                        onChange={(event) =>
                            setData('code', event.target.value)
                        }
                        placeholder="BAR"
                    />
                </Field>
                <Field
                    label="Description"
                    htmlFor="plan-description"
                    error={errors.description}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="plan-description"
                        rows={2}
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Cancellation policy"
                    htmlFor="plan-policy"
                    error={errors.cancellation_policy_id}
                    required
                >
                    <Select
                        value={data.cancellation_policy_id}
                        onValueChange={(value) =>
                            setData('cancellation_policy_id', value)
                        }
                    >
                        <SelectTrigger id="plan-policy">
                            <SelectValue placeholder="Choose a policy" />
                        </SelectTrigger>
                        <SelectContent>
                            {policies.map((policy) => (
                                <SelectItem
                                    key={policy.id}
                                    value={String(policy.id)}
                                >
                                    {policy.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    label="Rate adjustment (%)"
                    htmlFor="plan-adjustment"
                    error={errors.adjustment_percent}
                    required
                >
                    <Input
                        id="plan-adjustment"
                        inputMode="decimal"
                        value={data.adjustment_percent}
                        onChange={(event) =>
                            setData('adjustment_percent', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Minimum nights"
                    htmlFor="plan-min"
                    error={errors.min_nights}
                    required
                >
                    <Input
                        id="plan-min"
                        type="number"
                        min={1}
                        value={data.min_nights}
                        onChange={(event) =>
                            setData('min_nights', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Deposit (%)"
                    htmlFor="plan-deposit"
                    error={errors.deposit_percent}
                    hint="Blank uses the hotel default."
                >
                    <Input
                        id="plan-deposit"
                        inputMode="decimal"
                        value={data.deposit_percent}
                        onChange={(event) =>
                            setData('deposit_percent', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Display order"
                    htmlFor="plan-order"
                    error={errors.display_order}
                    required
                >
                    <Input
                        id="plan-order"
                        type="number"
                        min={0}
                        value={data.display_order}
                        onChange={(event) =>
                            setData('display_order', event.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="flex flex-wrap gap-6">
                <Toggle
                    id="plan-refundable"
                    label="Refundable"
                    checked={data.is_refundable}
                    onChange={(value) => setData('is_refundable', value)}
                />
                <Toggle
                    id="plan-breakfast"
                    label="Includes breakfast"
                    checked={data.includes_breakfast}
                    onChange={(value) => setData('includes_breakfast', value)}
                />
                <Toggle
                    id="plan-public"
                    label="Offered online"
                    checked={data.is_public}
                    onChange={(value) => setData('is_public', value)}
                />
                <Toggle
                    id="plan-active"
                    label="Active"
                    checked={data.is_active}
                    onChange={(value) => setData('is_active', value)}
                />
                <Toggle
                    id="plan-default"
                    label="Default plan"
                    checked={data.is_default}
                    onChange={(value) => setData('is_default', value)}
                />
            </div>
        </FormDialog>
    );
}

function SeasonDialog({
    season,
    onClose,
    roomTypes,
    currency,
}: {
    season: Season | null | undefined;
    onClose: () => void;
    roomTypes: { id: number; name: string }[];
    currency: string;
}) {
    const initial = {
        room_type_id: '',
        name: '',
        start_date: '',
        end_date: '',
        rate: '',
        weekend_rate: '',
        priority: '0',
        is_active: true,
    };
    const form = useRecordForm(season, initial, () => ({
        room_type_id: String(season!.room_type_id),
        name: season!.name,
        start_date: season!.start_date,
        end_date: season!.end_date,
        rate: season!.rate.amount,
        weekend_rate: season!.weekend_rate?.amount ?? '',
        priority: String(season!.priority),
        is_active: season!.is_active,
    }));
    const { data, setData, errors } = form;

    return (
        <FormDialog
            open={season !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={season ? `Edit ${season.name}` : 'New seasonal rate'}
            description="Overrides the standard nightly rate for every night from the first to the last date, inclusive. The highest priority wins where seasons overlap."
            processing={form.processing}
            onSubmit={() =>
                save(
                    form as never,
                    !season,
                    admin.seasonalRates.store.url(),
                    season ? admin.seasonalRates.update.url(season.id) : null,
                    onClose,
                )
            }
        >
            <FormErrorSummary errors={errors} />
            <Field
                label="Room type"
                htmlFor="season-type"
                error={errors.room_type_id}
                required
            >
                <Select
                    value={data.room_type_id}
                    onValueChange={(value) => setData('room_type_id', value)}
                >
                    <SelectTrigger id="season-type">
                        <SelectValue placeholder="Choose a room type" />
                    </SelectTrigger>
                    <SelectContent>
                        {roomTypes.map((type) => (
                            <SelectItem key={type.id} value={String(type.id)}>
                                {type.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            <Field
                label="Season name"
                htmlFor="season-name"
                error={errors.name}
                required
            >
                <Input
                    id="season-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    placeholder="Independence Day"
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="First night"
                    htmlFor="season-start"
                    error={errors.start_date}
                    required
                >
                    <Input
                        id="season-start"
                        type="date"
                        value={data.start_date}
                        onChange={(event) =>
                            setData('start_date', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Last night"
                    htmlFor="season-end"
                    error={errors.end_date}
                    required
                >
                    <Input
                        id="season-end"
                        type="date"
                        min={data.start_date || undefined}
                        value={data.end_date}
                        onChange={(event) =>
                            setData('end_date', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label={`Nightly rate (${currency})`}
                    htmlFor="season-rate"
                    error={errors.rate}
                    required
                >
                    <Input
                        id="season-rate"
                        inputMode="decimal"
                        value={data.rate}
                        onChange={(event) =>
                            setData('rate', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label={`Fri & Sat rate (${currency})`}
                    htmlFor="season-weekend"
                    error={errors.weekend_rate}
                >
                    <Input
                        id="season-weekend"
                        inputMode="decimal"
                        value={data.weekend_rate}
                        onChange={(event) =>
                            setData('weekend_rate', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Priority"
                    htmlFor="season-priority"
                    error={errors.priority}
                    required
                >
                    <Input
                        id="season-priority"
                        type="number"
                        min={0}
                        value={data.priority}
                        onChange={(event) =>
                            setData('priority', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Toggle
                id="season-active"
                label="Active"
                checked={data.is_active}
                onChange={(value) => setData('is_active', value)}
            />
        </FormDialog>
    );
}

function PolicyDialog({
    policy,
    onClose,
    penaltyTypes,
}: {
    policy: Policy | null | undefined;
    onClose: () => void;
    penaltyTypes: Option[];
}) {
    const initial = {
        name: '',
        description: '',
        free_cancellation_hours: '48',
        penalty_type: 'first_night',
        penalty_percent: '',
        is_default: false,
    };
    const form = useRecordForm(policy, initial, () => ({
        name: policy!.name,
        description: policy!.description ?? '',
        free_cancellation_hours: String(policy!.free_cancellation_hours),
        penalty_type: policy!.penalty_type,
        penalty_percent:
            policy!.penalty_type === 'percentage'
                ? policy!.penalty_percent
                : '',
        is_default: policy!.is_default,
    }));
    const { data, setData, errors } = form;

    return (
        <FormDialog
            open={policy !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={policy ? `Edit ${policy.name}` : 'New cancellation policy'}
            description="Each reservation keeps a copy of the policy it was booked under."
            processing={form.processing}
            onSubmit={() =>
                save(
                    form as never,
                    !policy,
                    admin.cancellationPolicies.store.url(),
                    policy
                        ? admin.cancellationPolicies.update.url(policy.id)
                        : null,
                    onClose,
                )
            }
        >
            <FormErrorSummary errors={errors} />
            <Field
                label="Name"
                htmlFor="policy-name"
                error={errors.name}
                required
            >
                <Input
                    id="policy-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                />
            </Field>
            <Field
                label="Guest-facing description"
                htmlFor="policy-description"
                error={errors.description}
            >
                <Textarea
                    id="policy-description"
                    rows={2}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Free until (hours before arrival)"
                    htmlFor="policy-hours"
                    error={errors.free_cancellation_hours}
                    required
                    className="sm:col-span-3"
                >
                    <Input
                        id="policy-hours"
                        type="number"
                        min={0}
                        value={data.free_cancellation_hours}
                        onChange={(event) =>
                            setData(
                                'free_cancellation_hours',
                                event.target.value,
                            )
                        }
                    />
                </Field>
                <Field
                    label="Charge afterwards"
                    htmlFor="policy-penalty"
                    error={errors.penalty_type}
                    required
                    className="sm:col-span-2"
                >
                    <Select
                        value={data.penalty_type}
                        onValueChange={(value) =>
                            setData('penalty_type', value)
                        }
                    >
                        <SelectTrigger id="policy-penalty">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {penaltyTypes.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                {data.penalty_type === 'percentage' && (
                    <Field
                        label="Percent"
                        htmlFor="policy-percent"
                        error={errors.penalty_percent}
                        required
                    >
                        <Input
                            id="policy-percent"
                            inputMode="decimal"
                            value={data.penalty_percent}
                            onChange={(event) =>
                                setData('penalty_percent', event.target.value)
                            }
                        />
                    </Field>
                )}
            </div>
            <Toggle
                id="policy-default"
                label="Default policy"
                checked={data.is_default}
                onChange={(value) => setData('is_default', value)}
            />
        </FormDialog>
    );
}

function AmenityDialog({
    amenity,
    onClose,
}: {
    amenity: Amenity | null | undefined;
    onClose: () => void;
}) {
    const initial = {
        name: '',
        icon: '',
        category: 'room',
        is_active: true,
        display_order: '0',
    };
    const form = useRecordForm(amenity, initial, () => ({
        name: amenity!.name,
        icon: amenity!.icon ?? '',
        category: amenity!.category,
        is_active: amenity!.is_active,
        display_order: String(amenity!.display_order),
    }));
    const { data, setData, errors } = form;

    return (
        <FormDialog
            open={amenity !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={amenity ? `Edit ${amenity.name}` : 'New amenity'}
            processing={form.processing}
            onSubmit={() =>
                save(
                    form as never,
                    !amenity,
                    admin.amenities.store.url(),
                    amenity ? admin.amenities.update.url(amenity.slug) : null,
                    onClose,
                )
            }
        >
            <Field
                label="Name"
                htmlFor="amenity-name"
                error={errors.name}
                required
            >
                <Input
                    id="amenity-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Icon"
                    htmlFor="amenity-icon"
                    error={errors.icon}
                    hint="Lucide name, e.g. wifi"
                >
                    <Input
                        id="amenity-icon"
                        value={data.icon}
                        onChange={(event) =>
                            setData('icon', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Category"
                    htmlFor="amenity-category"
                    error={errors.category}
                    required
                >
                    <Select
                        value={data.category}
                        onValueChange={(value) => setData('category', value)}
                    >
                        <SelectTrigger id="amenity-category">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="room">Room</SelectItem>
                            <SelectItem value="hotel">Hotel</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    label="Order"
                    htmlFor="amenity-order"
                    error={errors.display_order}
                    required
                >
                    <Input
                        id="amenity-order"
                        type="number"
                        min={0}
                        value={data.display_order}
                        onChange={(event) =>
                            setData('display_order', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Toggle
                id="amenity-active"
                label="Visible"
                checked={data.is_active}
                onChange={(value) => setData('is_active', value)}
            />
        </FormDialog>
    );
}

RatesIndex.layout = {
    breadcrumbs: [{ title: 'Rates & policies', href: admin.rates.index() }],
};
