<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ShiftNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Handover notes between shifts, shown on the dashboard.
 */
class ShiftNoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::DashboardView->value);

        $data = $request->validate([
            'topic' => ['nullable', 'string', 'max:80'],
            'body' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $note = new ShiftNote($data);
        $note->user_id = $request->user()->id;
        $note->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Handover note added.')]);

        return back();
    }
}
