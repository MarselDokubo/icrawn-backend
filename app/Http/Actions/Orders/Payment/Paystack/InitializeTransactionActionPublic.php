<?php

namespace HiEvents\Http\Actions\Orders\Payment\Paystack;

use HiEvents\Exceptions\Paystack\InitializeTransactionFailedException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Paystack\InitializeTransactionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InitializeTransactionActionPublic extends BaseAction
{
    public function __construct(private InitializeTransactionHandler $handler) {}

    public function __invoke(Request $request, int $eventId, string $orderShortId): JsonResponse
    {
        // Validate request safely without relying on a missing helper
        $payload = Validator::make($request->all(), [
            'email'        => ['required', 'email'],
            'callback_url' => ['nullable', 'url'],
        ])->validate();

        try {
            $tx = $this->handler->handle(
                $eventId,
                $orderShortId,
                $payload['email'],
                $payload['callback_url'] ?? null
            );
        } catch (InitializeTransactionFailedException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->jsonResponse([
            'authorization_url' => $tx->authorizationUrl,
            'reference'         => $tx->reference,
            'access_code'       => $tx->accessCode,
        ]);
    }
}
