<?php

namespace HiEvents\Http\Actions\EventSettings;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\EventSettings\UpdateEventSettingsRequest;
use HiEvents\Resources\Event\EventSettingsResource;
use HiEvents\Services\Application\Handlers\EventSettings\DTO\PartialUpdateEventSettingsDTO;
use HiEvents\Services\Application\Handlers\EventSettings\PartialUpdateEventSettingsHandler;
use Illuminate\Http\JsonResponse;
use Throwable;
use HiEvents\Exceptions\TxnStepException;


class PartialEditEventSettingsAction extends BaseAction
{
    public function __construct(
        private readonly PartialUpdateEventSettingsHandler $partialUpdateEventSettingsHandler
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(UpdateEventSettingsRequest $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        try {
            $event = $this->partialUpdateEventSettingsHandler->handle(
                PartialUpdateEventSettingsDTO::fromArray([
                    'settings'   => $request->validated(),
                    'event_id'   => $eventId,
                    'account_id' => $this->getAuthenticatedAccountId(),
                ]),
            );
        } catch (TxnStepException $e) { // 👈 ADD THIS
            return response()->json([
                'ok'       => false,
                'where'    => $e->step,
                'sql'      => $e->sql,
                'code'     => $e->sqlState ?? (string)$e->getCode(),
                'bindings' => $e->bindings,
                'message'  => $e->getMessage(),
            ], 422);
        }

        return $this->resourceResponse(EventSettingsResource::class, $event);
    }
}
