<?php

namespace App\Models;

use App\Enums\CancellationPenalty;
use Database\Factories\CancellationPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $free_cancellation_hours
 * @property CancellationPenalty $penalty_type
 * @property string $penalty_percent
 * @property bool $is_default
 */
#[Fillable(['name', 'description', 'free_cancellation_hours', 'penalty_type', 'penalty_percent', 'is_default'])]
class CancellationPolicy extends Model
{
    /** @use HasFactory<CancellationPolicyFactory> */
    use HasFactory;

    /**
     * Immutable copy stored on each reservation so later policy edits never
     * change the terms a guest agreed to.
     *
     * @return array{id: int, name: string, description: string|null, free_cancellation_hours: int, penalty_type: string, penalty_percent: string}
     */
    public function snapshot(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'penalty_type' => $this->penalty_type->value,
            'penalty_percent' => (string) $this->penalty_percent,
        ];
    }

    /**
     * Human-readable summary of the policy terms.
     */
    public function summary(): string
    {
        return match ($this->penalty_type) {
            CancellationPenalty::None => 'Free cancellation at any time before arrival.',
            CancellationPenalty::FullStay => 'Non-refundable: the full stay is charged on cancellation.',
            default => "Free cancellation up to {$this->free_cancellation_hours} hours before arrival; afterwards "
                .($this->penalty_type === CancellationPenalty::FirstNight
                    ? 'the first night is charged.'
                    : rtrim(rtrim((string) $this->penalty_percent, '0'), '.').'% of the stay is charged.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'penalty_type' => CancellationPenalty::class,
            'penalty_percent' => 'decimal:2',
            'free_cancellation_hours' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
