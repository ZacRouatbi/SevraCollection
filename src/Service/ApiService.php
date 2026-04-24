<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiService
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private string $apiBaseUrl,
        private string $apiKey,
        private string $apiSecret,
    ) {
        $this->client = HttpClient::create();
    }

    public function getProducts(): array
    {
        return [];
    }

    public function getProduct(string $slug): ?array
    {
        return null;
    }

    public function getNewArrivals(): array
    {
        return [];
    }

    public function login(string $email, string $password): ?string
    {
        return null;
    }

    public function register(array $data): ?array
    {
        return null;
    }

    public function resetPassword(string $email): bool
    {
        return false;
    }

    public function getCustomer(int $id): ?array
    {
        return null;
    }

    public function updateCustomer(int $id, array $data): ?array
    {
        return null;
    }

    public function createOrder(array $data): ?array
    {
        return null;
    }
}
