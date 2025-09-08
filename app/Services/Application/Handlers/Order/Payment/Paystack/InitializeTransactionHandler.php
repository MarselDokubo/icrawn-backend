<?php
namespace HiEvents\Services\Application\Handlers\Order\Payment\Paystack;

use HiEvents\Models\PaystackPayment;
use HiEvents\Services\Domain\Payment\Paystack\PaystackTransactionService;
use HiEvents\Services\Domain\Payment\Paystack\DTOs\InitializeTransactionResponseDTO;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use HiEvents\Exceptions\Paystack\InitializeTransactionFailedException;
use Throwable;

class InitializeTransactionHandler
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private CheckoutSessionManagementService $session,
        private PaystackTransactionService $paystack,
    ) {}

    public function handle(
        int $eventId,
        string $orderShortId,
        string $email,
        ?string $callbackUrl = null
    ): InitializeTransactionResponseDTO {
        $order = $this->orders
            ->loadRelation(new Relationship(OrderItemDomainObject::class))
            ->findByShortId($orderShortId);

        if (!$order || !$this->session->verifySession($order->getSessionId())) {
            throw new \HiEvents\Exceptions\UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
        }

        if ($order->getStatus() !== OrderStatus::RESERVED->name || $order->isReservedOrderExpired()) {
            throw new \HiEvents\Exceptions\ResourceConflictException(__('Sorry, the order is expired or not in a valid state.'));
        }

        $reference = PaystackTransactionService::newReference('ord_');

        $params = [
            'email'     => $email,
            'amount'    => PaystackTransactionService::koboFromFloat($order->getTotalGross()),
            'currency'  => env('PAYSTACK_CURRENCY', 'NGN'),
            'reference' => $reference,
            'metadata'  => [
                'order_short_id' => $order->getShortId(),
                'event_id'       => $order->getEventId(),
                'order_id'       => $order->getId(),
            ],
        ];

        // Prefer explicit callback_url from the request; otherwise use env fallback
        $cb = $callbackUrl ?: env('PAYSTACK_CALLBACK_URL');
        if ($cb) {
            $qs = http_build_query([
                'event_id'       => $order->getEventId(),
                'order_short_id' => $order->getShortId(),
                'reference'      => $reference,
            ]);
            $params['callback_url'] = $cb . (str_contains($cb, '?') ? '&' : '?') . $qs;
        }

        try {
            $init = $this->paystack->initialize($params);
        } catch (Throwable $e) {
            throw new InitializeTransactionFailedException($e->getMessage(), 0, $e);
        }

        PaystackPayment::create([
            'order_id'          => $order->getId(),
            'reference'         => $init->reference,
            'access_code'       => $init->accessCode,
            'authorization_url' => $init->authorizationUrl,
            'status'            => 'initialized',
            'amount'            => $params['amount'],
            'payload'           => $params,
        ]);

        return $init; // DTO
    }
}
