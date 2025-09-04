<?php

namespace HiEvents\Resources\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Resources\Organizer\OrganizerResourcePublic;
use HiEvents\Resources\ProductCategory\ProductCategoryResourcePublic;
use HiEvents\Resources\Question\QuestionResource;
use Illuminate\Http\Request;

/**
 * @mixin EventDomainObject
 */
class EventResourcePublic extends BaseResource
{
    private readonly bool $includePostCheckoutData;

    public function __construct(
        mixed $resource,
        mixed $includePostCheckoutData = false,
    )
    {
        // This is a hacky workaround to handle when this resource is instantiated
        // internally within Laravel the second param is the collection key (numeric)
        // When called normally, second param is includePostCheckoutData (boolean)
        $this->includePostCheckoutData = is_bool($includePostCheckoutData)
            ? $includePostCheckoutData
            : false;

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getId(),
            'title' => $this->resource->getTitle(),
            'category' => $this->resource->getCategory(),
            'description' => $this->resource->getDescription(),
            'description_preview' => $this->resource->getDescriptionPreview(),
            'start_date' => $this->resource->getStartDate(),
            'end_date' => $this->resource->getEndDate(),
            'currency' => $this->resource->getCurrency(),
            'slug' => $this->resource->getSlug(),
            'status' => $this->resource->getStatus(),
            'lifecycle_status' => $this->resource->getLifecycleStatus(),
            'timezone' => $this->resource->getTimezone(),
            'location_details' => $this->when((bool)$this->resource->getLocationDetails(), fn() => $this->resource->getLocationDetails()),
            'product_categories' => $this->when(
                condition: !is_null($this->resource->getProductCategories()) && $this->resource->getProductCategories()->isNotEmpty(),
                value: fn() => ProductCategoryResourcePublic::collection($this->resource->getProductCategories()),
            ),
            'settings' => $this->when(
                condition: !is_null($this->resource->getEventSettings()),
                value: fn() => new EventSettingsResourcePublic(
                    $this->resource->getEventSettings(),
                    $this->includePostCheckoutData
                ),
            ),
            // @TODO - public question resource
            'questions' => $this->when(
                condition: !is_null($this->resource->getQuestions()),
                value: fn() => QuestionResource::collection($this->resource->getQuestions())
            ),
            'attributes' => $this->when(
                condition: !is_null($this->resource->getAttributes()),
                value: fn() => collect($this->resource->getAttributes())->reject(fn($attribute) => !$attribute['is_public'])),
            'images' => $this->when(
                condition: !is_null($this->resource->getImages()),
                value: fn() => ImageResource::collection($this->resource->getImages())
            ),
            'organizer' => $this->when(
                condition: !is_null($this->resource->getOrganizer()),
                value: fn() => new OrganizerResourcePublic($this->resource->getOrganizer()),
            ),
        ];
    }
}
