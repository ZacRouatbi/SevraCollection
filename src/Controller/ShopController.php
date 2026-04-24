<?php

namespace App\Controller;

use App\Service\WooCommerceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShopController extends AbstractController
{
    #[Route('/shop', name: 'shop')]
    public function index(Request $request, WooCommerceService $woo): Response
    {
        $categorySlug = $request->query->get('category');
        $search       = $request->query->get('q');
        $sort         = $request->query->get('sort', 'date');   // date | price_asc | price_desc

        // Fetch all top-level product categories (excluding uncategorized)
        $categories = $woo->getCategories();

        // Fetch products with active filters
        $products = $woo->getFilteredProducts(
            categorySlug: $categorySlug,
            search:       $search,
            sort:         $sort,
            perPage:      24,
        );

        return $this->render('pages/shop/index.twig', [
            'products'         => $products,
            'categories'       => $categories,
            'active_category'  => $categorySlug,
            'search_query'     => $search,
            'active_sort'      => $sort,
        ]);
    }

    #[Route('/shop/{slug}', name: 'shop_product_detail')]
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
