<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'shop')]
    public function index(Request $request): Response
    {
        // TODO: fetch products from API with filters
        $type   = $request->query->get('type');   // 'pants' | 'shorts'
        $search = $request->query->get('q');

        return $this->render('pages/shop/index.twig', [
            'products'       => [],
            'active_type'    => $type,
            'search_query'   => $search,
        ]);
    }

    #[Route('/shop/{slug}', name: 'product_detail')]
    public function show(string $slug): Response
    {
        // TODO: fetch single product from API
        return $this->render('pages/shop/product.twig', [
            'product' => null,
            'slug'    => $slug,
        ]);
    }
}
