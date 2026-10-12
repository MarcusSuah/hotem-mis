<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * Adapter for one payment provider's webhooks. A provider is added by
 * implementing this interface and registering it in config/hms.php.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Whether the request genuinely comes from the provider (signature and
     * freshness). Unverified requests are never stored or processed.
     */
    public function verify(Request $request): bool;

    /**
     * @throws InvalidGatewayPayload
     */
    public function parse(Request $request): GatewayEvent;
}
