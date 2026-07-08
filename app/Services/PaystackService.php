<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    private string $secretKey;

    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret', '');
    }

    public function initializeTransaction(
        string $email,
        int $amountKobo,
        string $reference,
        string $callbackUrl,
        array $metadata = []
    ): array {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email'        => $email,
                'amount'       => $amountKobo,
                'reference'    => $reference,
                'callback_url' => $callbackUrl,
                'metadata'     => $metadata ?: null,
            ]);

        if (!$response->successful() || !($response->json('status'))) {
            throw new \RuntimeException(
                'Paystack initialization failed: ' . $response->json('message', 'Unknown error')
            );
        }

        return $response->json('data');
    }

    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($expected, $signature);
    }
}
