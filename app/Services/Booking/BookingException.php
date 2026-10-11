<?php

namespace App\Services\Booking;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A booking business rule was violated (capacity, availability, status).
 * Rendered like a validation failure so forms keep the user's input.
 */
class BookingException extends DomainException
{
    public function __construct(string $message, public readonly string $field = 'booking')
    {
        parent::__construct($message);
    }

    public static function roomUnavailable(string $field = 'room_id'): self
    {
        return new self('The selected room is no longer available for these dates. Please choose another room.', $field);
    }

    public static function noAvailability(): self
    {
        return new self('No rooms of this type are available for the selected dates.', 'room_type_id');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("A {$from} reservation cannot be changed to {$to}.", 'status');
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $this->getMessage(), 'errors' => [$this->field => [$this->getMessage()]]], 422);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back()->withErrors([$this->field => $this->getMessage()])->withInput();
    }
}
