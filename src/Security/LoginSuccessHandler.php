<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\CartService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * After a successful login:
 *  1. Snapshot the guest cart (captured before Symfony regenerates the session).
 *  2. Let Symfony migrate the session (it copies all data automatically).
 *  3. Merge the snapshot back so nothing is lost.
 *  4. Redirect to the intended target path or home.
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly CartService           $cartService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        // Snapshot guest cart BEFORE session migration wipes it
        $guestItems = $this->cartService->getItems();

        // Merge guest items into the (now authenticated) session cart
        if (!empty($guestItems)) {
            $this->cartService->mergeFrom($guestItems);
        }

        // Honour the saved target path (e.g. user tried to go to /checkout)
        $session = $request->getSession();
        if ($session->has('_security.main.target_path')) {
            $targetPath = $session->get('_security.main.target_path');
            $session->remove('_security.main.target_path');
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }
}
