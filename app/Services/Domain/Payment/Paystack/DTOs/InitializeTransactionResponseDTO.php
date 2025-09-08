<?php

namespace HiEvents\Services\Domain\Payment\Paystack\DTOs;

class InitializeTransactionResponseDTO
{
    public function __construct(
        public readonly string $authorizationUrl,
        public readonly string $reference,
        public readonly ?string $accessCode = null,
    ) {}
}
