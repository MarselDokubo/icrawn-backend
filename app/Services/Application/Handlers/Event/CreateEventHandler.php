<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Domain\Event\CreateEventService;
use HiEvents\Services\Domain\Organizer\OrganizerFetchService;
use HiEvents\Services\Domain\ProductCategory\CreateProductCategoryService;
use HiEvents\Support\TxnProbe;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Throwable;

class CreateEventHandler
{
    public function __construct(
        private readonly CreateEventService           $createEventService,
        private readonly OrganizerFetchService        $organizerFetchService,
        private readonly CreateProductCategoryService $createProductCategoryService,
        private readonly DatabaseManager              $databaseManager,
    ) {
    }

    /**
     * @throws OrganizerNotFoundException
     * @throws Throwable
     */
    public function handle(CreateEventDTO $eventData): EventDomainObject
    {
        // Optional: disable the DB transaction to expose the first failing statement (avoid 25P02 masking)
        $noTxn  = request()->headers->get('X-Debug-NoTxn') === '1';
        $runner = fn () => $this->createEvent($eventData);

        return $noTxn ? $runner() : $this->databaseManager->transaction($runner);
    }

    /**
     * @throws OrganizerNotFoundException
     * @throws Throwable
     */
    private function createEvent(CreateEventDTO $eventData): EventDomainObject
    {
        // 1) Verify organizer (ownership/FK guard)
        $organizer = TxnProbe::step('organizers.fetch', fn () =>
            $this->organizerFetchService->fetchOrganizer(
                organizerId: $eventData->organizer_id,
                accountId:   $eventData->account_id
            )
        );

        $event = (new EventDomainObject())
            ->setOrganizerId($eventData->organizer_id)
            ->setAccountId($eventData->account_id)
            ->setUserId($eventData->user_id)
            ->setTitle($eventData->title)
            ->setStartDate($eventData->start_date)
            ->setEndDate($eventData->end_date)
            ->setDescription($eventData->description)
            ->setAttributes($eventData->attributes?->toArray())
            ->setTimezone($eventData->timezone ?? $organizer->getTimezone())
            ->setCurrency($eventData->currency ?? $organizer->getCurrency())
            ->setCategory($eventData->category?->value ?? EventCategory::OTHER->value)
            ->setStatus($eventData->status)
            ->setEventSettings($eventData->event_settings)
            ->setLocationDetails($eventData->location_details?->toArray());

        // 3) Persist the event
        $newEvent = TxnProbe::step('events.create', fn () =>
            $this->createEventService->createEvent($event)
        );

        // 4) Create default product category (catch duplicate unique violations)
        try {
            TxnProbe::step('product_categories.create_default', fn () =>
                $this->createProductCategoryService->createDefaultProductCategory($newEvent)
            );
        } catch (QueryException $e) {
            // 23505 = unique_violation (PostgreSQL)
            if ((string)$e->getCode() !== '23505') {
                throw $e;
            }
        }

        return $newEvent;
    }
}
