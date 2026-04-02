<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalClient
{
    protected function config(string $key): mixed
    {
        return config("services.paypal.$key");
    }

    protected function baseUrl(): string
    {
        $sandbox = (bool) $this->config('sandbox');

        return $sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    protected function tokenRequest(): PendingRequest
    {
        $clientId = (string) $this->config('client_id');
        $clientSecret = (string) $this->config('secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('PayPal is not configured.');
        }

        return Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->baseUrl($this->baseUrl())
            ->acceptJson();
    }

    protected function apiRequest(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->baseUrl($this->baseUrl())
            ->acceptJson();
    }

    protected function accessToken(): string
    {
        $response = $this->tokenRequest()->post('/v1/oauth2/token', [
            'grant_type' => 'client_credentials',
        ])->throw();

        $token = (string) $response->json('access_token');

        if ($token === '') {
            throw new RuntimeException('Unable to retrieve PayPal access token.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->apiRequest()
            ->post('/v2/checkout/orders', $payload)
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $orderId): array
    {
        return $this->apiRequest()
            ->post("/v2/checkout/orders/{$orderId}/capture")
            ->throw()
            ->json();
    }

    public function extractCaptureAmountCents(array $captureResponse): int
    {
        $value = (string) Arr::get(
            $captureResponse,
            'purchase_units.0.payments.captures.0.amount.value',
            '0'
        );

        return (int) round(((float) $value) * 100);
    }

    public function extractCurrency(array $captureResponse): string
    {
        return strtoupper((string) Arr::get(
            $captureResponse,
            'purchase_units.0.payments.captures.0.amount.currency_code',
            ''
        ));
    }

    public function extractCaptureId(array $captureResponse): string
    {
        return (string) Arr::get($captureResponse, 'purchase_units.0.payments.captures.0.id', '');
    }
}
