<?php

namespace App\Http\Controllers\Admin\Identity;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::AuditLogsView->value);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:96'],
            'user' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->whereLike('action', "{$action}%"))
            ->when($filters['user'] ?? null, fn ($query, string $user) => $query->whereHas(
                'user',
                fn ($users) => $users->whereLike('name', "%{$user}%")->orWhereLike('email', "%{$user}%"),
            ))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<', now()->parse($to)->addDay()))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->user?->only(['id', 'name', 'email']),
                'subject' => $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : null,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/audit-logs/index', [
            'logs' => $logs,
            'filters' => [
                'action' => $filters['action'] ?? '',
                'user' => $filters['user'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }
}
