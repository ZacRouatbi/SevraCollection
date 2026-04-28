<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\NewsletterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {}

    #[Route('/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type');
        if (!str_contains($contentType, 'application/json')) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid content type',
            ], 415);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $name  = trim($data['name'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid email address',
            ], 400);
        }

        try {
            $result = $this->newsletterService->subscribe($email, $name);
            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }
}
