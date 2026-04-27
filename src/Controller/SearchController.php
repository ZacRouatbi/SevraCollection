<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\WooCommerceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    public function __construct(private readonly WooCommerceService $wooCommerce) {}



    /** Search products by query string */
    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $products = $this->wooCommerce->getFilteredProducts(
            categorySlug: null,
            search: $q,
            sort: 'date',
            perPage: 8
        );

        // Return minimal data with image, name, price, slug
        $results = array_map(fn($p) => [
            'name'  => $p['name'] ?? '',
            'slug'  => $p['slug'] ?? '',
            'price' => $p['price'] ?? '',
            'image' => $p['images'][0] ?? '/images/placeholder.jpg',
        ], $products);

        return $this->json($results);
    }
}
