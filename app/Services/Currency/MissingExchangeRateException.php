<?php

namespace App\Services\Currency;

use App\Enums\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * No exchange rate has been recorded for a conversion the user asked for.
 * Rendered like a validation failure so the form stays open with a clear
 * instruction instead of an error page.
 */
class MissingExchangeRateException extends RuntimeException
{
    public function __construct(public readonly Currency $from, public readonly Currency $to)
    {
        parent::__construct("No exchange rate is configured for {$from->value} to {$to->value}.");
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        $message = "There is no {$this->from->value} to {$this->to->value} exchange rate yet. Record today's rate under Finance → Exchange Rates, then try again.";

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message, 'errors' => ['currency' => [$message]]], 422);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back()->withErrors(['currency' => $message])->withInput();
    }
}
