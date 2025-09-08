<?php

namespace HiEvents\Services\Application\DTO\Payment\Paystack;

class InitializeTransactionResponseDTO
{
    public function __construct(
        public ?string $authorizationUrl,
        public ?string $reference,
        public string $status,
        public string $message
    ) {}
}
