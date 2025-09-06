<?php

namespace HiEvents\Http\Actions\Events;

use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Exceptions\TxnStepException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Event\CreateEventRequest;
use HiEvents\Resources\Event\EventResource;
use HiEvents\Services\Application\Handlers\Event\CreateEventHandler;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateEventAction extends BaseAction
{
    public function __construct(
        private readonly CreateEventHandler $createEventHandler
    ) {
    }

    /**
     * @throws ValidationException|Throwable
     */
    public function __invoke(CreateEventRequest $request): JsonResponse
    {
        $authorisedUser = $this->getAuthenticatedUser();

        $eventData = array_merge(
            $request->validated(),
            [
                'account_id' => $this->getAuthenticatedAccountId(),
                'user_id'    => $authorisedUser->getId(),
            ]
        );

        try {
            $event = $this->createEventHandler->handle(
                eventData: CreateEventDTO::fromArray($eventData)
            );
        } catch (OrganizerNotFoundException $e) {
            throw ValidationException::withMessages([
                'organizer_id' => $e->getMessage(),
            ]);
        } catch (TxnStepException $e) {
            // Rich debug payload when requested
            if ($request->headers->get('X-Debug') === '1') {
                return response()->json([
                    'ok'       => false,
                    'where'    => $e->step,
                    'sql'      => $e->sql,
                    'code'     => $e->sqlState ?? (string)$e->getCode(),
                    'bindings' => $e->bindings,
                    'message'  => $e->getMessage(),
                    'debug_header_seen' => $request->headers->get('X-Debug-NoTxn'),
                    'handler_no_txn'    => request()->headers->get('X-Debug-NoTxn') === '1',
                    'db_tx_level'       => DB::connection()->transactionLevel(), // <— here

                ], 500);
            }
            throw $e;
        }

        return $this->resourceResponse(EventResource::class, $event);
    }
}
