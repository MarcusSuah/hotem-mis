<?php

namespace App\Jobs;

use App\Models\PaymentGatewayEvent;
use App\Services\Payments\GatewayEventProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies a stored webhook outside the request, retrying transient errors.
 */
class ProcessPaymentGatewayEvent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public PaymentGatewayEvent $event) {}

    public function uniqueId(): string
    {
        return (string) $this->event->id;
    }

    public function handle(GatewayEventProcessor $processor): void
    {
        $processor->process($this->event);
    }
}
