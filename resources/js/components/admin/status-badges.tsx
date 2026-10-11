import { Badge } from '@/components/ui/badge';

type BadgeVariant =
    | 'success'
    | 'warning'
    | 'danger'
    | 'gold'
    | 'muted'
    | 'secondary'
    | 'default';

const reservationVariants: Record<string, BadgeVariant> = {
    pending: 'warning',
    confirmed: 'default',
    checked_in: 'success',
    checked_out: 'muted',
    cancelled: 'danger',
    no_show: 'danger',
};

const paymentVariants: Record<string, BadgeVariant> = {
    unpaid: 'warning',
    partially_paid: 'gold',
    paid: 'success',
    partially_refunded: 'muted',
    refunded: 'muted',
};

const roomStateVariants: Record<string, BadgeVariant> = {
    available: 'success',
    reserved: 'gold',
    occupied: 'default',
    maintenance: 'warning',
    out_of_service: 'danger',
    blocked: 'muted',
};

const roomStateLabels: Record<string, string> = {
    available: 'Available',
    reserved: 'Reserved',
    occupied: 'Occupied',
    maintenance: 'Under Maintenance',
    out_of_service: 'Out of Service',
    blocked: 'Blocked',
};

const housekeepingVariants: Record<string, BadgeVariant> = {
    dirty: 'danger',
    cleaning: 'warning',
    clean: 'secondary',
    inspected: 'success',
};

export function ReservationStatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return (
        <Badge variant={reservationVariants[status] ?? 'secondary'}>
            {label}
        </Badge>
    );
}

export function PaymentStatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return (
        <Badge variant={paymentVariants[status] ?? 'secondary'}>{label}</Badge>
    );
}

export function RoomStateBadge({ state }: { state: string }) {
    return (
        <Badge variant={roomStateVariants[state] ?? 'secondary'}>
            {roomStateLabels[state] ?? state}
        </Badge>
    );
}

export function HousekeepingBadge({ status }: { status: string }) {
    return (
        <Badge
            variant={housekeepingVariants[status] ?? 'secondary'}
            className="capitalize"
        >
            {status.replace('_', ' ')}
        </Badge>
    );
}
