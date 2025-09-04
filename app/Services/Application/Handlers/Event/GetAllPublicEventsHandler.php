<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Services\Application\Handlers\Event\DTO\GetAllPublicEventsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAllPublicEventsHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
    ) {}

    public function handle(GetAllPublicEventsDTO $dto): LengthAwarePaginator
    {
        // You may want to add pagination, filtering, etc. here
        return $this->eventRepository
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(EventStatisticDomainObject::class))
            ->loadRelation(new Relationship(
                domainObject: OrganizerDomainObject::class,
                name: 'organizer',
            ))
            ->findAllPublicEvents($dto->queryParams);
    }
}
