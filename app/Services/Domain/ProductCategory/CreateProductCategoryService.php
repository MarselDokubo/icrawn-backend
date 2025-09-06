<?php

namespace HiEvents\Services\Domain\ProductCategory;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use HiEvents\Support\TxnProbe;

class CreateProductCategoryService
{
    public function __construct(
        private readonly ProductCategoryRepositoryInterface $productCategoryRepository,
    ) {
    }

    public function createCategory(ProductCategoryDomainObject $productCategoryDomainObject): ProductCategoryDomainObject
    {
        return $this->productCategoryRepository->create(array_filter($productCategoryDomainObject->toArray()));
    }

    public function createDefaultProductCategory(EventDomainObject $event, bool $noTxn = false): void
    {
        // No transaction here; just wrap the insert so failures are reported cleanly
        TxnProbe::step('product_categories.insert_default', fn () =>
            $this->createCategory((new ProductCategoryDomainObject())
                ->setEventId($event->getId())
                ->setName(__('Tickets'))
                ->setIsHidden(false)
                ->setNoProductsMessage(__('There are no tickets available for this event'))
            )
        );
    }
}
