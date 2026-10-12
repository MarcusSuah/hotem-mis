<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\SystemRole;
use App\Exceptions\ProtectedAccountException;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property Currency|null $preferred_currency
 * @property bool $is_protected
 * @property bool $is_active
 * @property CarbonImmutable|null $deactivated_at
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read string|null $avatar_url
 */
#[Fillable(['name', 'email', 'phone', 'password', 'preferred_currency'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Mirrors the column defaults so freshly created models behave like stored ones.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_protected' => false,
    ];

    /**
     * The protected Super Admin can never be deleted, deactivated or unprotected,
     * regardless of which code path attempts it.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            if ($user->is_protected) {
                throw ProtectedAccountException::cannotDelete();
            }
        });

        static::updating(function (User $user): void {
            if (! $user->getOriginal('is_protected')) {
                return;
            }

            if ($user->isDirty('is_protected') && ! $user->is_protected) {
                throw ProtectedAccountException::cannotUnprotect();
            }

            if ($user->isDirty('is_active') && ! $user->is_active) {
                throw ProtectedAccountException::cannotDeactivate();
            }
        });
    }

    public function isProtectedSuperAdmin(): bool
    {
        return $this->is_protected;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(SystemRole::SuperAdmin->value);
    }

    /**
     * The guest profile linked to this login, if any.
     *
     * @return HasOne<Guest, $this>
     */
    public function guest(): HasOne
    {
        return $this->hasOne(Guest::class);
    }

    /**
     * @return HasOne<StaffProfile, $this>
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /**
     * Staff accounts may enter the administration area.
     */
    public function isStaff(): bool
    {
        return $this->getRoleNames()->contains(fn (string $role): bool => $role !== SystemRole::Guest->value);
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'preferred_currency' => Currency::class,
            'is_protected' => 'boolean',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
