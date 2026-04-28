<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        HttpClientInterface $client,
        CsrfTokenManagerInterface $csrfTokenManager,
        RateLimiterService $rateLimiter
    ): Response
    {
        $isAjax = $request->isXmlHttpRequest();

        // Get form data
        $email = trim($request->request->get('email', ''));
        $password = $request->request->get('password', '');
        $firstName = trim($request->request->get('first_name', ''));
        $lastName = trim($request->request->get('last_name', ''));
        $csrfToken = $request->request->get('_csrf_token', '');

        // ✅ Rate limiting for registration
        $clientIp = $request->getClientIp() ?? 'unknown';
        $rateKey = 'register_' . $clientIp;
        $rateCheck = $rateLimiter->isLimited($rateKey, 3, 3600); // 3 attempts per hour

        if ($rateCheck['limited']) {
            $error = 'Trop de tentatives. Veuillez réessayer dans ' . $rateCheck['retry_after'] . ' secondes.';
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => $error], 429);
            }
            $this->addFlash('error', $error);
            return $this->redirectToRoute('home');
        }

        // ✅ CSRF validation
        if (!$this->isCsrfTokenValid('register', $csrfToken)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Jeton CSRF invalide. Veuillez rafraîchir la page.'], 403);
            }
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('home');
        }

        // ✅ Validation
        $errors = [];

        if (empty($firstName) || strlen($firstName) < 2) {
            $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
        }

        if (empty($lastName) || strlen($lastName) < 2) {
            $errors[] = 'Le nom doit contenir au moins 2 caractères.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse email invalide.';
        }

        $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/';
        if (!preg_match($passwordRegex, $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial.';
        }

        if (!empty($errors)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'errors' => $errors], 400);
            }
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('home');
        }

        // ✅ Create WordPress/WooCommerce user
        try {
            $wpUrl = rtrim($_ENV['WORDPRESS_URL'] ?? 'https://your-wordpress-site.com', '/');
            $consumerKey = $_ENV['WC_CONSUMER_KEY'] ?? '';
            $consumerSecret = $_ENV['WC_CONSUMER_SECRET'] ?? '';

            if (empty($consumerKey) || empty($consumerSecret)) {
                throw new \Exception('Configuration API manquante');
            }

            $response = $client->request('POST', $wpUrl . '/wp-json/wc/v3/customers', [
                'auth_basic' => [$consumerKey, $consumerSecret],
                'json' => [
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $email,
                    'password' => $password,
                ],
                'timeout' => 15,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 201) {
                // Success - reset rate limiter
                $rateLimiter->reset($rateKey);

                if ($isAjax) {
                    return $this->json([
                        'success' => true,
                        'message' => 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.'
                    ]);
                }
                $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('home');
            }

            // Handle error response
            $data = $response->toArray(false);
            $errorMessage = $data['message'] ?? 'Erreur lors de la création du compte.';

            // Handle duplicate email case
            if (str_contains($errorMessage, 'unique') || str_contains($errorMessage, 'already exists')) {
                $errorMessage = 'Un compte existe déjà avec cette adresse email.';
            }

            if ($isAjax) {
                return $this->json(['success' => false, 'message' => $errorMessage], 400);
            }
            $this->addFlash('error', $errorMessage);

        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->json(['success' => false, 'message' => 'Erreur de connexion au service. Veuillez réessayer plus tard.'], 500);
            }
            $this->addFlash('error', 'Erreur de connexion au service. Veuillez réessayer plus tard.');
        }

        return $this->redirectToRoute('home');
    }
}
