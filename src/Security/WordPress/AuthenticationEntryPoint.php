<?php

declare(strict_types=1);

namespace App\Security\WordPress;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {}

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // Store the authentication error in session for display in modal
        if ($request->hasSession() && $authException) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $authException);
        }

        // If it's an AJAX request, return a 401 response
        if ($request->isXmlHttpRequest()) {
            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        // Redirect to home page (which contains the login modal)
        return new RedirectResponse($this->urlGenerator->generate('home'));
    }
}
