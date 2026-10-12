<?php

namespace App\Http\Requests\Site;

use App\Models\Offer;
use App\Services\Booking\StayPeriod;
use App\Services\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Dates and party size for a public availability search.
 */
class BookingSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date_format:Y-m-d', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'offer' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_in_date.after_or_equal' => 'Choose an arrival date from today onwards.',
            'check_out_date.after' => 'Departure must be after arrival.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $settings = app(SettingsRepository::class);
            $maxNights = (int) $settings->get('booking.max_stay_nights', 30);
            $maxAdvance = (int) $settings->get('booking.max_advance_days', 365);

            if ($this->stay()->nights() > $maxNights) {
                $validator->errors()->add('check_out_date', "Online bookings are limited to {$maxNights} nights. Please contact us for longer stays.");
            }

            if ($this->stay()->checkIn->greaterThan(CarbonImmutable::today()->addDays($maxAdvance))) {
                $validator->errors()->add('check_in_date', "We take reservations up to {$maxAdvance} days ahead.");
            }
        }];
    }

    public function stay(): StayPeriod
    {
        return new StayPeriod($this->string('check_in_date')->toString(), $this->string('check_out_date')->toString());
    }

    public function adults(): int
    {
        return $this->integer('adults', 1);
    }

    public function children(): int
    {
        return $this->integer('children');
    }

    public function offer(): ?Offer
    {
        return $this->filled('offer')
            ? Offer::query()->current()->with('ratePlan')->where('slug', $this->string('offer')->toString())->first()
            : null;
    }
}
