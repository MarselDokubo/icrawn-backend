<?php

namespace HiEvents\DomainObjects\Generated;

abstract class PaystackPaymentDomainObjectAbstract
{
    public const TABLE             = 'paystack_payments';

    public const ID                = 'id';
    public const ORDER_ID          = 'order_id';
    public const REFERENCE         = 'reference';
    public const AUTHORIZATION_URL = 'authorization_url';
    public const ACCESS_CODE       = 'access_code';
}
