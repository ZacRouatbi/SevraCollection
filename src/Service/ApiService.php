<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ApiService – all calls to the WordPress/WooCommerce REST backend go here.
 * TODO: implement all methods once the API is live.
 */
class ApiService
{
    public function __construct(
        private HttpClientInterface $client,
        private string $baseUrl
    ) {}

    public function getProducts(array $filters = []): array
    {
        // TODO: GET /wp-json/wc/v3/products
        return [];
    }

    public function getProduct(string $slug): ?array
    {
        // TODO: GET /wp-json/wc/v3/products?slug={slug}
        return null;
    }

    public function getNewArrivals(): array
    {
        // TODO: GET /wp-json/wc/v3/products?tag=new-arrivals
        return [];
    }

    public function login(string $email, string $password): ?array
    {
        // TODO: POST /wp-json/jwt-auth/v1/token
        return null;
    }

    public function register(array $data): ?array
    {
        // TODO: POST /wp-json/wc/v3/customers
        return null;
    }

    public function resetPassword(string $email): bool
    {
        // TODO: POST /wp-json/sevra/v1/reset-password
        return false;
    }

    public function getCustomer(int $id): ?array
    {
        // TODO: GET /wp-json/wc/v3/customers/{id}
        return null;
    }

    public function updateCustomer(int $id, array $data): ?array
    {
        // TODO: PUT /wp-json/wc/v3/customers/{id}
        return null;
    }

    public function createOrder(array $orderData): ?array
    {
        // TODO: POST /wp-json/wc/v3/orders
        return null;
    }
}
