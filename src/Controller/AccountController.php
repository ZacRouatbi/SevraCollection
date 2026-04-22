<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountController extends AbstractController
{
    #[Route('/account', name: 'account')]
    public function index(): Response
    {
        // TODO: load user from session/API
        return $this->render('pages/account/index.twig', [
            'user'   => null,
            'orders' => [],
        ]);
    }

    #[Route('/account/update', name: 'account_update', methods: ['POST'])]
    public function update(Request $request): Response
    {
        // TODO: POST updated info to API
        return $this->redirectToRoute('account');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): Response
    {
        // TODO: clear session, call API logout
        return $this->redirectToRoute('home');
    }
}
