<?php

namespace HiEvents\Services\Domain\Payment\Paystack\EventHandlers;

use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\Models\PaystackPayment;

class ChargeSuccessHandler
{
    public function __construct(private OrderRepositoryInterface $orders) {}

    public function handle(array $payload): void
    {
        $data = $payload['data'] ?? [];
        $reference = $data['reference'] ?? null;
        if (!$reference) return;

        // Optional: tie back to the PaystackPayment we created during initialize
        $ps = PaystackPayment::query()->where('reference', $reference)->first();

        // Find the order id from our stored row (or from metadata if you prefer)
        $orderId = $ps?->order_id ?? ($data['metadata']['order_id'] ?? null);
        if (!$orderId) return;

        // Mark order as paid (mirrors the Stripe success flow)
        $this->orders->updateFromArray($orderId, [
            OrderDomainObjectAbstract::PAYMENT_STATUS   => OrderPaymentStatus::PAYMENT_RECEIVED->name,
            OrderDomainObjectAbstract::STATUS           => OrderStatus::COMPLETED->name,
            OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::PAYSTACK->name,
        ]);

        // If your paystack_payments table has extra columns (status/fees/payload) you can save them here.
        // With your current migration, you can safely just touch the row:
        if ($ps) {
            $ps->touch();
        }
    }
}
