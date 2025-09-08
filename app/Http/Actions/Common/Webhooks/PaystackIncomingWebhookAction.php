<?php
namespace HiEvents\Http\Actions\Common\Webhooks;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Paystack\IncomingWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaystackIncomingWebhookAction extends BaseAction
{
    public function __invoke(Request $request, IncomingWebhookHandler $handler): JsonResponse
    {
        $raw = $request->getContent();
        $sig = $request->header('x-paystack-signature');

        // signature = sha512(rawBody, secretKey)
        $secret = config('paystack.secret');
        if (!$secret || !$sig || hash_hmac('sha512', $raw, $secret) !== $sig) {
            return $this->unauthorizedResponse('Invalid signature');
        }

        $payload = json_decode($raw, true) ?? [];
        $handler->handle($payload);

        return $this->jsonResponse(['ok' => true]);
    }
}
