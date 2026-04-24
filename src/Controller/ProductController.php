<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\WooCommerceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/produit/{slug}', name: 'product_detail')]
    public function show(string $slug, WooCommerceService $woo): Response
    {
        $product = $woo->getProductBySlug($slug);

        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('pages/product/show.twig', [
            'product' => $product,
        ]);
    }
}
