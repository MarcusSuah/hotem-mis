import { Head, router, useForm } from '@inertiajs/react';
import { MessageSquareQuote } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Stars } from '@/components/site/site-ui';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Review = {
    id: number;
    rating: number;
    title: string;
    body: string;
    management_response: string | null;
    status: string;
    guest: string;
    byline: string;
    reservation: string;
    stay: string;
    moderator: string | null;
    created_at: string | null;
};

type Props = {
    reviews: Paginated<Review>;
    filters: { status: string };
    statuses: Option[];
    counts: Record<string, number>;
};

export default function ReviewModeration({
    reviews,
    filters,
    statuses,
    counts,
}: Props) {
    const [moderating, setModerating] = useState<{
        review: Review;
        status: string;
    } | null>(null);

    return (
        <>
            <Head title="Guest reviews" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Guest reviews"
                    description="Reviews come only from guests who completed a stay. Nothing is public until it is approved here."
                />
                <div
                    className="flex flex-wrap gap-2"
                    role="group"
                    aria-label="Filter by status"
                >
                    {statuses.map((status) => (
                        <button
                            key={status.value}
                            type="button"
                            aria-pressed={filters.status === status.value}
                            onClick={() =>
                                router.get(
                                    admin.website.reviews.index.url(),
                                    { status: status.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                            className={cn(
                                'rounded-full border px-4 py-1.5 text-sm',
                                filters.status === status.value
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'hover:bg-muted',
                            )}
                        >
                            {status.label} ({counts[status.value] ?? 0})
                        </button>
                    ))}
                </div>
                <div className="rounded-xl border bg-card shadow-xs">
                    {reviews.data.length === 0 ? (
                        <EmptyState
                            icon={MessageSquareQuote}
                            title="Nothing here"
                            description="No reviews with this status."
                        />
                    ) : (
                        <ul className="divide-y">
                            {reviews.data.map((review) => (
                                <li
                                    key={review.id}
                                    className="space-y-2 p-5 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Stars rating={review.rating} />
                                        <span className="text-xs text-muted-foreground">
                                            {review.guest} (shown as{' '}
                                            {review.byline}) ·{' '}
                                            {review.reservation} · {review.stay}{' '}
                                            ·{' '}
                                            {formatDateTime(review.created_at)}
                                        </span>
                                    </div>
                                    <p className="font-medium">
                                        {review.title}
                                    </p>
                                    <p className="whitespace-pre-line text-muted-foreground">
                                        {review.body}
                                    </p>
                                    {review.management_response && (
                                        <p className="rounded-lg bg-muted/60 p-3">
                                            Response:{' '}
                                            {review.management_response}
                                        </p>
                                    )}
                                    {review.moderator && (
                                        <p className="text-xs text-muted-foreground">
                                            Moderated by {review.moderator}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap gap-2 pt-1">
                                        {review.status !== 'approved' && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    setModerating({
                                                        review,
                                                        status: 'approved',
                                                    })
                                                }
                                            >
                                                Approve
                                            </Button>
                                        )}
                                        {review.status !== 'rejected' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setModerating({
                                                        review,
                                                        status: 'rejected',
                                                    })
                                                }
                                            >
                                                Don’t publish
                                            </Button>
                                        )}
                                        {review.status === 'approved' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setModerating({
                                                        review,
                                                        status: 'approved',
                                                    })
                                                }
                                            >
                                                Edit response
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={reviews} />
                    </div>
                </div>
            </div>
            <ModerateDialog
                moderating={moderating}
                onClose={() => setModerating(null)}
            />
        </>
    );
}

function ModerateDialog({
    moderating,
    onClose,
}: {
    moderating: { review: Review; status: string } | null;
    onClose: () => void;
}) {
    const form = useForm({ status: '', management_response: '' });

    useEffect(() => {
        if (moderating) {
            form.clearErrors();
            form.setData({
                status: moderating.status,
                management_response:
                    moderating.review.management_response ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [moderating]);

    return (
        <FormDialog
            open={moderating !== null}
            onOpenChange={(open) => !open && onClose()}
            title={
                moderating?.status === 'approved'
                    ? 'Publish review'
                    : 'Keep review private'
            }
            description={
                moderating?.status === 'approved'
                    ? 'The review appears on the website with the guest’s first name and last initial.'
                    : 'The review stays on record but is never shown publicly.'
            }
            submitLabel={moderating?.status === 'approved' ? 'Publish' : 'Save'}
            processing={form.processing}
            onSubmit={() =>
                moderating &&
                form.put(
                    admin.website.reviews.update.url(moderating.review.id),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                        onError: notifyValidationFailed,
                    },
                )
            }
        >
            <Field
                label="Public response from the hotel (optional)"
                htmlFor="rv-response"
                error={form.errors.management_response}
            >
                <Textarea
                    id="rv-response"
                    rows={4}
                    value={form.data.management_response}
                    onChange={(event) =>
                        form.setData('management_response', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

ReviewModeration.layout = {
    breadcrumbs: [
        { title: 'Guest reviews', href: admin.website.reviews.index() },
    ],
};
