<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Events;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\Resources\Event\EventResourcePublic;
use \HiEvents\Services\Application\Handlers\Event\DTO\GetAllPublicEventsDTO;
use HiEvents\Services\Application\Handlers\Event\GetAllPublicEventsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class GetAllEventsPublicAction extends BaseAction
{
    public function __construct(
        private readonly GetAllPublicEventsHandler $handler,
    ) {} 

    public function __invoke(Request $request): JsonResponse
    {
        $events = $this->handler->handle(
            GetAllPublicEventsDTO::fromArray([
                'queryParams' => $this->getPaginationQueryParams($request)
            ])
        );
        return $this->filterableResourceResponse(
            resource: EventResourcePublic::class,
            data: $events,
            domainObject: \HiEvents\DomainObjects\EventDomainObject::class,
        );
    }
}
