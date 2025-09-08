<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\PaystackPaymentDomainObjectAbstract as Defs;

class PaystackPaymentDomainObject
{
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
        private readonly string $reference,
        private readonly string $authorizationUrl,
        private readonly ?string $accessCode = null,
    ) {}

    public function getId(): int { return $this->id; }
    public function getOrderId(): int { return $this->orderId; }
    public function getReference(): string { return $this->reference; }
    public function getAuthorizationUrl(): string { return $this->authorizationUrl; }
    public function getAccessCode(): ?string { return $this->accessCode; }

    // Convenience for Order relation name used in handler
    public function getTable(): string { return Defs::TABLE; }
}
