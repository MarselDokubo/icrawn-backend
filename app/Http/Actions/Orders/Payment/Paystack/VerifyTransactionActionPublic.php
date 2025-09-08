<?php

namespace HiEvents\Http\Actions\Orders\Payment\Paystack;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Order\Payment\Paystack\VerifyTransactionHandler;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyTransactionActionPublic extends BaseAction
{
    public function __construct(private VerifyTransactionHandler $handler) {}

    public function __invoke(string $eventId, string $orderShortId, string $reference): JsonResponse
    {
        try {
            $result = $this->handler->handle((int) $eventId, $orderShortId, $reference);
            return $this->jsonResponse($result);
        } catch (UnauthorizedException $e) {
            return $this->unauthorizedResponse($e->getMessage());
        } catch (ResourceConflictException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
