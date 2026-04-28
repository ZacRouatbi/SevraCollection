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
use App\Service\RateLimiterService;

class Authenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $wpUrl,
        private readonly RateLimiterService $rateLimiter
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $request->attributes->get('_route') === 'login_check';
    }

    public function authenticate(Request $request): Passport
    {
        // Rate limiting for login
        $clientIp = $request->getClientIp() ?? 'unknown';
        $rateKey = 'login_' . $clientIp;
        $rateCheck = $this->rateLimiter->isLimited($rateKey, 5, 300); // 5 attempts per 5 minutes

        if ($rateCheck['limited']) {
            throw new CustomUserMessageAuthenticationException(
                'Trop de tentatives de connexion. Veuillez réessayer dans ' .
                $rateCheck['retry_after'] . ' secondes.'
            );
        }

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
                throw new CustomUserMessageAuthenticationException('Données d\'authentification incompletes.');
            }

        } catch (TransportExceptionInterface $e) {
            throw new CustomUserMessageAuthenticationException('Connexion au service impossible.');
        }

        $jwtParts = explode('.', $token);
        $jwtPayload = json_decode(
            base64_decode(str_pad($jwtParts[1], strlen($jwtParts[1]) % 4, '=', STR_PAD_RIGHT)),
            true
        );
        $wpUserId = (int) ($jwtPayload['data']['user']['id'] ?? 0);
        $roles = [];
        if (isset($data['roles'])) {
            $roles = (array) $data['roles'];
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
        // Reset rate limiter on successful login
        $clientIp = $request->getClientIp() ?? 'unknown';
        $this->rateLimiter->reset('login_' . $clientIp);

        if ($request->getSession()->has('_security.main.target_path')) {
            $targetPath = $request->getSession()->get('_security.main.target_path');
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
            $request->getSession()->set('_security.last_error', $exception);
            $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $request->request->get('email'));
            $request->getSession()->set('_security.last_username', $request->request->get('email'));
        }

        $referer = $request->headers->get('referer');

        if ($referer && str_contains($referer, $request->getHost())) {
            return new RedirectResponse($referer);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }
}
