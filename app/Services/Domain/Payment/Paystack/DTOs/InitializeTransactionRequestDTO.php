<?php

namespace HiEvents\Services\Domain\Payment\Paystack\DTOs;

use HiEvents\Values\MoneyValue;

class InitializeTransactionRequestDTO
{
    public function __construct(
        public readonly MoneyValue $amount,
        public readonly string $currencyCode,
        public readonly object $order,   // Order domain object
        public readonly object $account, // Account domain object
        public readonly string $email,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'],
            currencyCode: $data['currencyCode'],
            order: $data['order'],
            account: $data['account'],
            email: $data['email'],
        );
    }
}
