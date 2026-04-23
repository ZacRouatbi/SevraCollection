<?php

declare(strict_types=1);

namespace App\Security\WordPress;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
class Authenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly UrlGeneratorInterface $urlGenerator,
         private readonly LoggerInterface $logger,
        private readonly string $wpUrl,

    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $request->attributes->get('_route') === 'login_check';
    }

    public function authenticate(Request $request): Passport
    {
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        if (empty($email) || empty($password)) {
            throw new CustomUserMessageAuthenticationException('Email et mot de passe requis.');
        }

        try {
            $response = $this->client->request('POST', rtrim($this->wpUrl, '/') . '/wp-json/jwt-auth/v1/token', [
                'json' => [
                    'username' => $email,
                    'password' => $password,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 403 || $statusCode === 401) {
                throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
            }

            if ($statusCode !== 200) {
                throw new CustomUserMessageAuthenticationException('Erreur du serveur d\'authentification.');
            }

            $data = $response->toArray();
            $token = $data['token'] ?? null;
            if (!$token) {
                throw new CustomUserMessageAuthenticationException('Données d\'authentification incomplètes.');
            }

        } catch (TransportExceptionInterface $e) {
            throw new CustomUserMessageAuthenticationException('Connexion au service impossible.');
        }
        $jwtParts = explode('.', $token);
        $payload = [];
        if (count($jwtParts) === 3) {
            $payload = json_decode(base64_decode($jwtParts[1]), true);
            $this->logger->error('JWT PAYLOAD', $payload);
        }

        $wpUserId = (int) ($payload['data']['user']['id'] ?? 0);
        $roles = [];
        if (isset($payload['roles'])) {
            $roles = (array) $payload['roles'];
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email, $token, $wpUserId, $roles) {
                return new User($email, $token, $wpUserId, $roles);
            }),
            [
                new CsrfTokenBadge('authenticate', $csrfToken)
            ]
            );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Check if there's a saved target path (user was trying to access protected page)
        if ($request->getSession()->has('_security.main.target_path')) {
            $targetPath = $request->getSession()->get('_security.main.target_path');
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
{
    if ($request->hasSession()) {
        // Store in both formats to ensure it's found
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        $request->getSession()->set('_security.last_error', $exception);
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $request->request->get('email'));
        $request->getSession()->set('_security.last_username', $request->request->get('email'));
    }

    // Get the referer URL
    $referer = $request->headers->get('referer');

    // If referer exists, redirect back to it
    if ($referer && str_contains($referer, $request->getHost())) {
        return new RedirectResponse($referer);
    }

    // Fallback to home page
    return new RedirectResponse($this->urlGenerator->generate('home'));
}
}
