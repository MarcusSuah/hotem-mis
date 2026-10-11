<?php

namespace App\Models;

use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $country_code
 * @property string|null $address
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $identity_type
 * @property string|null $identity_number
 * @property bool $is_vip
 * @property bool $marketing_consent
 * @property Currency|null $preferred_currency
 * @property string|null $notes
 * @property-read string $full_name
 */
#[Fillable([
    'user_id', 'first_name', 'last_name', 'email', 'phone', 'country_code', 'address', 'date_of_birth',
    'identity_type', 'identity_number', 'is_vip', 'marketing_consent', 'preferred_currency', 'notes',
])]
#[Hidden(['identity_number'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @param  Builder<Guest>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->whereLike('first_name', "%{$term}%")
            ->orWhereLike('last_name', "%{$term}%")
            ->orWhereLike('email', "%{$term}%")
            ->orWhereLike('phone', "%{$term}%"));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'identity_number' => 'encrypted',
            'is_vip' => 'boolean',
            'marketing_consent' => 'boolean',
            'preferred_currency' => Currency::class,
        ];
    }
}
