<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'cart')]
    public function index(): Response
    {
        // TODO: load cart from session or API
        return $this->render('pages/cart/index.twig', [
            'cart_items' => [],
            'subtotal'   => 0,
        ]);
    }
}
