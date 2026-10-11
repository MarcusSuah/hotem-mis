<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * Record an auditable action. Sensitive attribute names are redacted.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= $this->request->user();

        return AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->redact($oldValues) ?: null,
            'new_values' => $this->redact($newValues) ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
        ]);
    }

    /**
     * Record the changed attributes of a model that was just updated.
     */
    public function recordChanges(string $action, Model $subject, ?User $actor = null): ?AuditLog
    {
        $changes = collect($subject->getChanges())->except(['updated_at'])->all();

        if ($changes === []) {
            return null;
        }

        $original = collect($changes)
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => $subject->getRawOriginal($key)])
            ->all();

        return $this->record($action, $subject, $original, $changes, $actor);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        $fragments = config('hms.audit.redact', []);

        return collect($values)
            ->reject(fn (mixed $value, string $key): bool => Str::contains(Str::lower($key), $fragments))
            ->map(fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value)
            ->all();
    }
}
