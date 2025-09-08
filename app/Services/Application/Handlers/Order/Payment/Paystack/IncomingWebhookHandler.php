<?php
namespace HiEvents\Services\Application\Handlers\Order\Payment\Paystack;

use HiEvents\Services\Domain\Payment\Paystack\EventHandlers\ChargeSuccessHandler;

class IncomingWebhookHandler
{
    public function __construct(
        private ChargeSuccessHandler $chargeSuccess,
    ) {}

    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? null;

        if ($event === 'charge.success') {
            $this->chargeSuccess->handle($payload);
        }

        // You can handle charge.failed, refund, etc. later if needed.
    }
}
