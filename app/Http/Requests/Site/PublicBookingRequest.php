<?php

namespace App\Http\Requests\Site;

use App\Enums\Currency;
use App\Models\RoomType;
use App\Services\Website\PublicBooking;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A booking submitted from the website. Extends the search rules with the
 * guest's contact details; the hidden "website" field is a bot trap.
 */
class PublicBookingRequest extends BookingSearchRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'room_type' => ['required', 'string', 'exists:room_types,slug'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:32', 'regex:/^[0-9+()\-\s]+$/'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'accept_terms' => ['accepted'],
            'idempotency_key' => ['required', 'uuid'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'accept_terms.accepted' => 'Please accept the booking and cancellation terms.',
            'phone.regex' => 'Enter a phone number using digits, spaces and + only.',
            'website.prohibited' => 'Your booking could not be submitted.',
        ];
    }

    public function booking(Currency $currency): PublicBooking
    {
        return new PublicBooking(
            roomType: RoomType::query()->active()->where('slug', $this->string('room_type')->toString())->firstOrFail(),
            stay: $this->stay(),
            adults: $this->adults(),
            children: $this->children(),
            currency: $currency,
            firstName: $this->string('first_name')->trim()->toString(),
            lastName: $this->string('last_name')->trim()->toString(),
            email: $this->string('email')->lower()->trim()->toString(),
            phone: $this->string('phone')->trim()->toString(),
            countryCode: $this->filled('country_code') ? $this->string('country_code')->upper()->toString() : null,
            specialRequests: $this->filled('special_requests') ? $this->string('special_requests')->toString() : null,
            idempotencyKey: $this->string('idempotency_key')->toString(),
            offer: $this->offer(),
            user: $this->user(),
        );
    }
}
