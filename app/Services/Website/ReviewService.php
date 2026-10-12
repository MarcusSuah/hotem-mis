<?php

namespace App\Services\Website;

use App\Enums\ReservationStatus;
use App\Enums\ReviewStatus;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use Illuminate\Support\Facades\DB;

/**
 * Verified-stay reviews: only the guest who completed a stay may review it,
 * once, and nothing is published until a moderator approves it.
 */
class ReviewService
{
    public function __construct(private AuditLogger $audit) {}

    public function canReview(User $user, Reservation $reservation): bool
    {
        $user->loadMissing('guest');

        return $reservation->status === ReservationStatus::CheckedOut
            && $user->guest !== null
            && $reservation->guest_id === $user->guest->id
            && ! $reservation->review()->exists();
    }

    public function submit(User $user, Reservation $reservation, int $rating, string $title, string $body): Review
    {
        return DB::transaction(function () use ($user, $reservation, $rating, $title, $body): Review {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! $this->canReview($user, $locked)) {
                throw new BookingException('Reviews can be left once, after a completed stay.', 'rating');
            }

            return Review::create([
                'reservation_id' => $locked->id,
                'guest_id' => $locked->guest_id,
                'user_id' => $user->id,
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
            ]);
        });
    }

    public function moderate(Review $review, ReviewStatus $status, User $moderator, ?string $response = null): Review
    {
        $previous = $review->status;

        $review->forceFill([
            'status' => $status,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ]);

        if ($response !== null && trim($response) !== '' && $response !== $review->management_response) {
            $review->forceFill(['management_response' => trim($response), 'responded_at' => now()]);
        }

        $review->save();

        $this->audit->record('review.moderated', $review, ['status' => $previous->value], ['status' => $status->value], actor: $moderator);

        return $review;
    }
}
