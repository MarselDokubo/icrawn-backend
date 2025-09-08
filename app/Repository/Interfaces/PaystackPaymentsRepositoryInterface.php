<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\PaystackPaymentDomainObject;

interface PaystackPaymentsRepositoryInterface
{
    public function create(array $data): PaystackPaymentDomainObject;

    public function findByOrderId(int $orderId): ?PaystackPaymentDomainObject;
}
