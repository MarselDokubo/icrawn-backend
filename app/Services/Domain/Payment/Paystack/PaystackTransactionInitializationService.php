<?php

namespace HiEvents\Services\Domain\Payment\Paystack;

use HiEvents\Services\Domain\Payment\Paystack\DTOs\InitializeTransactionRequestDTO;
use HiEvents\Services\Domain\Payment\Paystack\DTOs\InitializeTransactionResponseDTO;
use HiEvents\Exceptions\Paystack\InitializeTransactionFailedException;
use Illuminate\Support\Facades\Http;

class PaystackTransactionInitializationService
{
    public function __construct(
        private readonly ?string $secret = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $callbackUrl = null,
    ) {
        // Allow env/config override via container if desired
        $this->secret      ??= config('paystack.secret');
        $this->baseUrl     ??= rtrim(config('paystack.base_url', 'https://api.paystack.co'), '/');
        $this->callbackUrl ??= config('paystack.callback_url'); // e.g. your backend verify endpoint
    }

    public function initialize(InitializeTransactionRequestDTO $dto): InitializeTransactionResponseDTO
    {
        if (!$this->secret) {
            throw new InitializeTransactionFailedException('Paystack secret key is missing.');
        }

        // Paystack expects lowest denomination (e.g., kobo)
        $amountMinor = (int) round($dto->amount->toFloat() * 100);

        // Optional: subaccount code for split payments (if your Account domain supports it)
        $subaccount = method_exists($dto->account, 'getPaystackSubaccountCode')
            ? $dto->account->getPaystackSubaccountCode()
            : null;

        $payload = array_filter([
            'email'       => $dto->email,
            'amount'      => $amountMinor,
            'currency'    => strtoupper($dto->currencyCode),
            'callback_url'=> $this->callbackUrl,
            'metadata'    => [
                'order_id'     => $dto->order->getId(),
                'order_short'  => $dto->order->getShortId(),
                'event_id'     => $dto->order->getEventId(),
                'session_id'   => $dto->order->getSessionId(),
            ],
            'subaccount'  => $subaccount,
        ], fn($v) => $v !== null && $v !== '');

        $resp = Http::withToken($this->secret)
            ->acceptJson()
            ->post($this->baseUrl . '/transaction/initialize', $payload);

        if (!$resp->ok() || !($data = $resp->json()) || empty($data['status'])) {
            throw new InitializeTransactionFailedException(
                'Failed to initialize Paystack transaction: ' . ($resp->body() ?: 'unknown error')
            );
        }

        $authUrl   = $data['data']['authorization_url'] ?? null;
        $reference = $data['data']['reference'] ?? null;
        $access    = $data['data']['access_code'] ?? null;

        if (!$authUrl || !$reference) {
            throw new InitializeTransactionFailedException('Paystack response missing authorization URL or reference.');
        }

        return new InitializeTransactionResponseDTO(
            authorizationUrl: $authUrl,
            reference: $reference,
            accessCode: $access
        );
    }
}
