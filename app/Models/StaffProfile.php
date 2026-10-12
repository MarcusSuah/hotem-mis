<?php

namespace App\Models;

use App\Enums\EmploymentType;
use Carbon\CarbonImmutable;
use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An employee record, separate from any login account.
 */
/**
 * @property int $id
 * @property int|null $user_id
 * @property int $department_id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 * @property string $job_title
 * @property EmploymentType $employment_type
 * @property CarbonImmutable $hire_date
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property bool $is_active
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string $full_name
 */
#[Fillable(['user_id', 'department_id', 'employee_number', 'first_name', 'last_name', 'job_title', 'employment_type', 'hire_date', 'email', 'phone', 'emergency_contact_name', 'emergency_contact_phone', 'is_active'])]
#[Hidden(['emergency_contact_name', 'emergency_contact_phone'])]
class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
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
            'employment_type' => EmploymentType::class,
            'hire_date' => 'immutable_date',
            'emergency_contact_name' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
