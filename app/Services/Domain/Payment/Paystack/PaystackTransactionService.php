<?php
namespace HiEvents\Services\Domain\Payment\Paystack;

use GuzzleHttp\Client;
use HiEvents\Services\Domain\Payment\Paystack\DTOs\InitializeTransactionResponseDTO;
use Illuminate\Support\Str;

class PaystackTransactionService
{
    public function __construct(private Client $http) {}

    public function initialize(array $params): InitializeTransactionResponseDTO
    {
        $base = rtrim(config('paystack.base_url', env('PAYSTACK_BASE_URL', 'https://api.paystack.co')), '/');

        $resp = $this->http->post("$base/transaction/initialize", [
            'headers' => [
                'Authorization' => 'Bearer ' . config('paystack.secret', env('PAYSTACK_SECRET_KEY')),
                'Content-Type'  => 'application/json',
            ],
            'json'        => $params,
            'http_errors' => false,
            'timeout'     => 20,
        ]);

        $data = json_decode((string) $resp->getBody(), true);
        if (($data['status'] ?? false) !== true) {
            $msg = $data['message'] ?? 'Unable to initialize Paystack transaction';
            throw new \RuntimeException($msg);
        }

        return new InitializeTransactionResponseDTO(
            authorizationUrl: $data['data']['authorization_url'],
            accessCode:       $data['data']['access_code'],
            reference:        $data['data']['reference']
        );
    }

    public function verify(string $reference): array
    {
        $base = rtrim(config('paystack.base_url', env('PAYSTACK_BASE_URL', 'https://api.paystack.co')), '/');

        $resp = $this->http->get("$base/transaction/verify/" . urlencode($reference), [
            'headers' => [
                'Authorization' => 'Bearer ' . config('paystack.secret', env('PAYSTACK_SECRET_KEY')),
            ],
            'http_errors' => false,
            'timeout'     => 20,
        ]);

        $data = json_decode((string) $resp->getBody(), true);
        if (!($data['status'] ?? false)) {
            $msg = $data['message'] ?? 'Verify failed';
            throw new \RuntimeException($msg);
        }

        return $data['data'];
    }

    public static function koboFromFloat(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function newReference(string $prefix = 'order_'): string
    {
        return $prefix . Str::uuid();
    }
}
