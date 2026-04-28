<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class NewsletterService
{
    private const ENDPOINT = '/wp-json/sevra/v1/newsletter/subscribe';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $wpUrl,
        private readonly string $wpApiSecret,
    ) {}

    /**
     * Subscribe an email to the newsletter via WordPress endpoint.
     *
     * @param string $email
     * @param string $name (optional)
     * @return array {success: bool, message: string}
     */
    public function subscribe(string $email, string $name = ''): array
    {
        try {
            $response = $this->client->request(
                'POST',
                rtrim($this->wpUrl, '/') . self::ENDPOINT,
                [
                    'headers' => [
                        'X-Sevra-Secret' => $this->wpApiSecret,
                        'Content-Type'   => 'application/json',
                    ],
                    'json' => [
                        'email' => $email,
                        'name'  => $name,
                    ],
                    'timeout' => 10,
                ]
            );

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $data = json_decode($content, true) ?? [];

            if ($statusCode === 200 || $statusCode === 201) {
                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Successfully subscribed',
                ];
            }

            // Handle already subscribed case
            if (($data['code'] ?? '') === 'already_subscribed') {
                return [
                    'success' => true,
                    'message' => 'Already subscribed',
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Subscription failed',
            ];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Newsletter subscription error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Service unavailable',
            ];
        }
    }
}
