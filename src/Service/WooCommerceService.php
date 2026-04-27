<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;

class WooCommerceService
{

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface     $logger,
        private readonly string              $wpUrl,
        private readonly string              $wcKey,
        private readonly string              $wcSecret,
    ) {}

    /**
     * Returns the latest $limit published products, newest first.
     * Each element is a plain array with the keys the template expects:
     *   slug, name, price, type, images[]
     */
    public function getLatestProducts(int $limit = 8): array
    {
        try {
            $response = $this->client->request(
                'GET',
                rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/products',
                [
                    'auth_basic' => [$this->wcKey, $this->wcSecret],
                    'query'      => [
                        'per_page' => $limit,
                        'orderby'  => 'date',
                        'order'    => 'desc',
                        'status'   => 'publish',
                    ],
                    'timeout' => 10,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('WooCommerce API error', [
                    'status' => $response->getStatusCode(),
                ]);
                return [];
            }

            $products = $response->toArray();

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('WooCommerce transport error', ['error' => $e->getMessage()]);
            return [];
        }

        return array_map(fn(array $p) => $this->normalize($p), $products);
    }

    private function normalize(array $p): array
    {
        // Derive a simple type label from categories
        $categories = array_column($p['categories'] ?? [], 'slug');
        $type = 'other';
        if (in_array('pantalon', $categories, true) || in_array('pants', $categories, true)) {
            $type = 'pants';
        } elseif (in_array('short', $categories, true) || in_array('shorts', $categories, true)) {
            $type = 'shorts';
        }

        return [
            'id'     => $p['id'] ?? 0,
            'slug'   => $p['slug'] ?? '',
            'name'   => $p['name'] ?? '',
            'price'  => $p['price'] ?? '',
            'type'   => $type,
            'images' => array_column($p['images'] ?? [], 'src'),
        ];
    }
    public function getProductBySlug(string $slug): ?array
{
    try {
        $response = $this->client->request(
            'GET',
            rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/products',
            [
                'auth_basic' => [$this->wcKey, $this->wcSecret],
                'query'      => [
                    'slug'     => $slug,
                    'per_page' => 1,
                    'status'   => 'publish',
                ],
                'timeout' => 10,
            ]
        );

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $products = $response->toArray();
        if (empty($products)) {
            return null;
        }

    } catch (TransportExceptionInterface $e) {
        $this->logger->error('WooCommerce product fetch error', ['error' => $e->getMessage()]);
        return null;
    }

    return $this->normalizeDetail($products[0]);
}

private function normalizeDetail(array $p): array
{
    $base = $this->normalize($p);

    // Sizes from attributes (WooCommerce attribute named "Taille" or "Size" or "pa_taille")
    $sizes = [];
    foreach ($p['attributes'] ?? [] as $attr) {
        $attrName = strtolower($attr['name'] ?? '');
        if (str_contains($attrName, 'taille') || str_contains($attrName, 'size')) {
            $sizes = $attr['options'] ?? [];
            break;
        }
    }

    // Colors from attributes
    $colors = [];
    foreach ($p['attributes'] ?? [] as $attr) {
        $attrName = strtolower($attr['name'] ?? '');
        if (str_contains($attrName, 'couleur') || str_contains($attrName, 'color')) {
            $colors = $attr['options'] ?? [];
            break;
        }
    }

    return array_merge($base, [
        'id'               => $p['id'] ?? 0,
        'description'      => strip_tags($p['description'] ?? ''),
        'short_description'=> strip_tags($p['short_description'] ?? ''),
        'regular_price'    => $p['regular_price'] ?? $p['price'] ?? '',
        'sale_price'       => $p['sale_price'] ?? '',
        'on_sale'          => $p['on_sale'] ?? false,
        'stock_status'     => $p['stock_status'] ?? 'instock',
        'sizes'            => $sizes,
        'colors'           => $colors,
        'gallery'          => array_column($p['images'] ?? [], 'src'),
        'categories'       => array_column($p['categories'] ?? [], 'name'),
        'sku'              => $p['sku'] ?? '',
    ]);
}
public function getCategories(): array
    {
        try {
            $response = $this->client->request(
                'GET',
                rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/products/categories',
                [
                    'auth_basic' => [$this->wcKey, $this->wcSecret],
                    'query'      => [
                        'per_page'   => 50,
                        'orderby'    => 'name',
                        'order'      => 'asc',
                        'hide_empty' => true,          // only cats that have products
                    ],
                    'timeout' => 10,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $cats = $response->toArray();

        } catch (\Throwable $e) {
            $this->logger->error('WooCommerce categories error', ['error' => $e->getMessage()]);
            return [];
        }

        return array_values(array_filter(
            array_map(fn(array $c) => [
                'id'    => $c['id'],
                'name'  => $c['name'],
                'slug'  => $c['slug'],
                'count' => $c['count'],
            ], $cats),
            fn(array $c) => $c['slug'] !== 'uncategorized' && $c['count'] > 0
        ));
    }

    /**
     * Returns products filtered by category slug, search string, and sort order.
     */
    public function getFilteredProducts(
        ?string $categorySlug = null,
        ?string $search       = null,
        string  $sort         = 'date',
        int     $perPage      = 24,
    ): array {
        // Resolve category slug → WC category ID (WC REST API filters by ID, not slug)
        $categoryId = null;
        if ($categorySlug) {
            $categories = $this->getCategories();
            foreach ($categories as $cat) {
                if ($cat['slug'] === $categorySlug) {
                    $categoryId = $cat['id'];
                    break;
                }
            }
            // If slug was provided but not found, return empty — avoids showing wrong products
            if (!$categoryId) {
                return [];
            }
        }

        // Map our sort tokens to WC REST API params
        $sortMap = [
            'date'       => ['orderby' => 'date',  'order' => 'desc'],
            'price_asc'  => ['orderby' => 'price', 'order' => 'asc'],
            'price_desc' => ['orderby' => 'price', 'order' => 'desc'],
        ];
        ['orderby' => $orderby, 'order' => $order] = $sortMap[$sort] ?? $sortMap['date'];

        $query = [
            'per_page' => $perPage,
            'orderby'  => $orderby,
            'order'    => $order,
            'status'   => 'publish',
        ];

        if ($categoryId) {
            $query['category'] = $categoryId;
        }

        if ($search) {
            $query['search'] = $search;
        }

        try {
            $response = $this->client->request(
                'GET',
                rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/products',
                [
                    'auth_basic' => [$this->wcKey, $this->wcSecret],
                    'query'      => $query,
                    'timeout'    => 12,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                $this->logger->error('WooCommerce products filter error', [
                    'status' => $response->getStatusCode(),
                ]);
                return [];
            }

            $products = $response->toArray();

        } catch (\Throwable $e) {
            $this->logger->error('WooCommerce products transport error', ['error' => $e->getMessage()]);
            return [];
        }

        return array_map(fn(array $p) => $this->normalize($p), $products);
    }
    public function getProductById(int $productId): ?array
    {
        try {
            $response = $this->client->request(
                'GET',
                rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/products/' . $productId,
                [
                    'auth_basic' => [$this->wcKey, $this->wcSecret],
                    'timeout'    => 10,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $this->normalizeDetail($response->toArray());

        } catch (\Throwable $e) {
            $this->logger->error('WooCommerce getProductById error', [
                'id'    => $productId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    /**
 * Returns up to $limit published products with the lowest stock,
 * excluding out-of-stock items. Sorted by stock_quantity ASC.
 *
 * @param int $limit Maximum number of products to return (default 5)
 * @return array
 */
public function getLowStockProducts(int $limit = 5): array
{
    try {
        $response = $this->client->request(
            'GET',
            rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/products',
            [
                'auth_basic' => [$this->wcKey, $this->wcSecret],
                'query'      => [
                    'per_page' => 100,            // fetch enough to sort locally
                    'status'   => 'publish',
                    'stock_status' => 'instock',  // only products currently in stock
                ],
                'timeout' => 10,
            ]
        );

        if ($response->getStatusCode() !== 200) {
            $this->logger->error('WooCommerce low stock API error', [
                'status' => $response->getStatusCode(),
            ]);
            return [];
        }

        $products = $response->toArray();

    } catch (TransportExceptionInterface $e) {
        $this->logger->error('WooCommerce low stock transport error', ['error' => $e->getMessage()]);
        return [];
    }

    // Filter out any that slipped through without a stock_quantity
    $products = array_filter($products, fn($p) => isset($p['stock_quantity']));

    // Sort by stock_quantity ascending (lowest first)
    usort($products, fn($a, $b) => ($a['stock_quantity'] ?? 0) <=> ($b['stock_quantity'] ?? 0));

    // Slice to the requested limit
    $products = array_slice($products, 0, $limit);

    // Normalize for the templates
    return array_map(fn(array $p) => $this->normalize($p), $products);
}
}
