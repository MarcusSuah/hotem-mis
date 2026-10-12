<?php

namespace App\Services\Payments;

use App\Services\Payments\Gateways\SignedWebhookGateway;

/**
 * Builds the gateways enabled in config/hms.php. A gateway without a
 * secret is treated as not configured, so its endpoint answers 404.
 */
class PaymentGatewayRegistry
{
    public function find(string $name): ?PaymentGateway
    {
        $config = config("hms.payments.gateways.{$name}");

        if (! is_array($config) || ! ($config['enabled'] ?? false) || blank($config['secret'] ?? null)) {
            return null;
        }

        return match ($config['driver'] ?? null) {
            'signed-webhook' => new SignedWebhookGateway($name, (string) $config['secret'], (int) ($config['tolerance'] ?? 300)),
            default => null,
        };
    }
}
