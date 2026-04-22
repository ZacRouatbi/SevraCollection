<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, redirect to home
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Render the home page with login modal data
        return $this->render('pages/home/index.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'show_login_modal' => $error !== null, // Flag to auto-open modal
        ]);
    }

    public function logout(): void
    {
        throw new \LogicException('This will be intercepted by the logout key on your firewall.');
    }
}
