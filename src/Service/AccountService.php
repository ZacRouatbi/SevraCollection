<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AccountService
{
    private const CACHE_TTL  = 300;
    private const CACHE_KEY  = 'sevra_account_data';
    private const ORDERS_KEY = 'sevra_orders_data';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $wpUrl,
        private readonly string $wpApiSecret
    ) {}

    public function getUserProfile(int $wpUserId, array &$session): ?array
    {
        try {
            $response = $this->client->request(
                'GET',
                rtrim($this->wpUrl, '/') . '/wp-json/sevra/v1/account/' . $wpUserId,
                [
                    'headers' => ['X-Sevra-Secret' => $this->wpApiSecret],
                ]
            );

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('getUserProfile failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getUserOrders(int $wpUserId, array &$session): array
    {
        try {
            $response = $this->client->request(
                'GET',
                rtrim($this->wpUrl, '/') . '/wp-json/sevra/v1/orders/' . $wpUserId,
                [
                    'headers' => ['X-Sevra-Secret' => $this->wpApiSecret],
                ]
            );

            return $response->toArray();

        } catch (\Exception $e) {
            $this->logger->error('getUserOrders failed: ' . $e->getMessage());
            return [];
        }
    }

    // ✅ FIXED CORE FUNCTION
    public function updateProfile(int $wpUserId, array $data, array &$session, ?string $token): array
    {
        try {
            $response = $this->client->request(
                'POST',
                rtrim($this->wpUrl, '/') . '/wp-json/sevra/v1/account/' . $wpUserId . '/update',
                [
                    'headers' => [
                        'Authorization' => $token ? 'Bearer ' . $token : '',
                        'X-Sevra-Secret' => $this->wpApiSecret,
                        'Content-Type'   => 'application/json',
                    ],
                    'json' => $data,
                ]
            );

            // ✅ CRITICAL DEBUG
            $status  = $response->getStatusCode();
            $content = $response->getContent(false);

            $this->logger->debug('WP update profile response', [
                'status' => $status,
                'content_length' => strlen($content),
            ]);

            $result = json_decode($content, true);

            if ($status === 200 && ($result['success'] ?? false)) {
                return ['success' => true, 'errors' => []];
            }

            return ['success' => false, 'errors' => $result['errors'] ?? ['Unknown error']];

        } catch (\Exception $e) {
            $this->logger->error('updateProfile HARD FAIL: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Symfony error']];
        }
    }

    public function updatePassword(int $wpUserId, string $currentPassword, string $newPassword, ?string $token): array
    {
        try {
            $response = $this->client->request(
                'POST',
                rtrim($this->wpUrl, '/') . '/wp-json/sevra/v1/account/' . $wpUserId . '/password',
                [
                    'headers' => [
                        'Authorization' => $token ? 'Bearer ' . $token : '',
                        'X-Sevra-Secret' => $this->wpApiSecret,
                        'Content-Type'   => 'application/json',
                    ],
                    'json' => [
                        'current_password' => $currentPassword,
                        'new_password'     => $newPassword,
                    ],
                ]
            );

            $content = $response->getContent(false);
            $result  = json_decode($content, true);

            return [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'Erreur',
            ];

        } catch (\Exception $e) {
            $this->logger->error('updatePassword failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur serveur'];
        }
    }
    public function validateProfileData(array $raw): array
{
    $errors = [];
    $data   = [];

    $firstName = trim(strip_tags($raw['first_name'] ?? ''));
    if (empty($firstName)) {
        $errors[] = 'Le prénom est requis.';
    } else {
        $data['first_name'] = $firstName;
    }

    $lastName = trim(strip_tags($raw['last_name'] ?? ''));
    if (empty($lastName)) {
        $errors[] = 'Le nom est requis.';
    } else {
        $data['last_name'] = $lastName;
    }

    $email = trim($raw['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    } else {
        $data['email'] = strtolower($email);
    }

    $data['phone']   = trim($raw['phone'] ?? '');
    $data['address'] = trim($raw['address'] ?? '');
    $data['city']    = trim($raw['city'] ?? '');
    $data['zip']     = trim($raw['zip'] ?? '');

    return ['data' => $data, 'errors' => $errors];
}
public function validatePasswordChange(array $raw): array
{
    $errors = [];

    $current = $raw['current_password'] ?? '';
    $new     = $raw['new_password'] ?? '';
    $confirm = $raw['confirm_password'] ?? '';

    $anyFilled = !empty($current) || !empty($new) || !empty($confirm);

    if (!$anyFilled) {
        return ['valid' => true, 'errors' => [], 'skip' => true];
    }

    if (empty($current)) {
        $errors[] = 'Mot de passe actuel requis.';
    }

    if (strlen($new) < 8) {
        $errors[] = 'Mot de passe trop court.';
    }

    if ($new !== $confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'skip' => false
    ];
}

}
