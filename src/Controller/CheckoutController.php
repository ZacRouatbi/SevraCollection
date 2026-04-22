<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutController extends AbstractController
{
    #[Route('/checkout', name: 'checkout')]
    public function index(): Response
    {
        // TODO: validate cart, load user info from session/API
        return $this->render('pages/checkout/index.twig', [
            'cart_items' => [],
            'subtotal'   => 0,
            'user'       => null,
        ]);
    }

    #[Route('/checkout/confirm', name: 'checkout_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        // TODO: submit order to API
        return $this->redirectToRoute('home');
    }
}
