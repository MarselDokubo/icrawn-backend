<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Paystack;

use HiEvents\Models\PaystackPayment;
use HiEvents\Services\Domain\Payment\Paystack\PaystackTransactionService;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;

class VerifyTransactionHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private PaystackTransactionService $paystack,
    ) {}

    /**
     * Verify Paystack transaction and finalize order.
     *
     * @param int $eventId
     * @param string $orderShortId
     * @param string $reference
     * @return array{verified: bool, status?: string, data?: mixed, reference?: string}
     */
    public function handle(int $eventId, string $orderShortId, string $reference): array
    {
        $order = $this->orders->findByShortId($orderShortId);
        if (!$order || $order->getEventId() !== $eventId) {
            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
        }

        // Ensure we know this reference for this order (created at initialize)
        $ps = PaystackPayment::query()
            ->where('reference', $reference)
            ->where('order_id', $order->getId())
            ->first();

        if (!$ps) {
            throw new ResourceConflictException(__('Unknown payment reference.'));
        }

        $data = $this->paystack->verify($reference);

        // Compare against the order total (stored in base currency units), converted to kobo
        $expected = PaystackTransactionService::koboFromFloat($order->getTotalGross());
        $paid     = (int) ($data['amount'] ?? 0);  // amount in kobo returned by Paystack
        $status   = $data['status'] ?? null;
        $currency = strtoupper($data['currency'] ?? 'NGN');

        // Not successful? just touch the row and return
        if ($status !== 'success') {
            $ps->touch();
            return ['verified' => false, 'status' => $status, 'data' => $data];
        }

        // Guard: amount/currency mismatch
        if ($paid !== $expected || $currency !== strtoupper((string) env('PAYSTACK_CURRENCY', 'NGN'))) {
            $ps->touch();
            throw new ResourceConflictException(__('Payment amount/currency mismatch.'));
        }

        // Transition order like Stripe’s PaymentIntentSucceededHandler
        $this->orders->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::PAYMENT_STATUS   => OrderPaymentStatus::PAYMENT_RECEIVED->name,
            OrderDomainObjectAbstract::STATUS           => OrderStatus::COMPLETED->name,
            OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::PAYSTACK->name,
        ]);

        // Optionally keep the payment row fresh
        $ps->touch();

        return ['verified' => true, 'status' => 'success', 'reference' => $reference];
    }
}
