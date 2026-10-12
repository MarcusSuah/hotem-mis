<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentGatewayEvent;
use App\Models\PaymentGatewayEvent;
use App\Services\Payments\InvalidGatewayPayload;
use App\Services\Payments\PaymentGatewayRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Receives payment provider notifications. Only signed, fresh requests are
 * stored; each event is stored once and applied by a queued job.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayRegistry $gateways): JsonResponse
    {
        $adapter = $gateways->find($gateway);

        abort_if($adapter === null, 404);

        if (! $adapter->verify($request)) {
            Log::warning('Rejected payment webhook with an invalid signature.', ['gateway' => $gateway, 'ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        try {
            $parsed = $adapter->parse($request);
        } catch (InvalidGatewayPayload $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        try {
            $event = PaymentGatewayEvent::create([
                'gateway' => $adapter->name(),
                'event_id' => $parsed->id,
                'type' => $parsed->type,
                'reservation_reference' => $parsed->reservationReference,
                'transaction_reference' => $parsed->transactionReference,
                'payload' => $this->redact($parsed->payload),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Already received.']);
        }

        ProcessPaymentGatewayEvent::dispatch($event);

        return response()->json(['message' => 'Received.'], 202);
    }

    /**
     * Drop card and secret fields a provider might include, before storage.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redact(array $payload): array
    {
        $sensitive = (array) config('hms.audit.redact', []);

        foreach (array_keys(Arr::dot($payload)) as $path) {
            foreach ($sensitive as $fragment) {
                if (str_contains(strtolower((string) $path), (string) $fragment)) {
                    Arr::set($payload, (string) $path, '[redacted]');
                }
            }
        }

        return $payload;
    }
}
