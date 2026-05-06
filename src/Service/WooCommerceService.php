<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

class WooCommerceService
{
    private const PRODUCT_CACHE_TTL = 300; // 5 minutes
    private const CATEGORY_CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface     $logger,
        private readonly string              $wpUrl,
        private readonly string              $wcKey,
        private readonly string              $wcSecret,
        private readonly string              $wpApiSecret,
        private readonly CacheInterface      $cache, // Added caching
    ) {}

    /**
     * Returns the latest $limit published products, newest first.
     * Each element is a plain array with the keys the template expects:
     *   slug, name, price, type, images[]
     */
    public function getLatestProducts(int $limit = 8): array
    {
        return $this->cache->get('wc_latest_products_' . $limit, function() use ($limit) {
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
        }, self::PRODUCT_CACHE_TTL);
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

        // Extract sizes from WC attributes (needed for the quick-add size picker on cards)
        $sizes = [];
        foreach ($p['attributes'] ?? [] as $attr) {
            $attrName = strtolower($attr['name'] ?? '');
            if (str_contains($attrName, 'taille') || str_contains($attrName, 'size')) {
                $sizes = $attr['options'] ?? [];
                break;
            }
        }

        return [
            'id'     => $p['id'] ?? 0,
            'slug'   => $p['slug'] ?? '',
            'name'   => $p['name'] ?? '',
            'price'  => $p['price'] ?? '',
            'type'   => $type,
            'images' => array_column($p['images'] ?? [], 'src'),
            'sizes'  => $sizes,
        ];
    }

    public function getProductBySlug(string $slug): ?array
    {
        return $this->cache->get('wc_product_slug_' . md5($slug), function() use ($slug) {
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
        }, self::PRODUCT_CACHE_TTL);
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
            'stock_quantity'   => $p['stock_quantity'] ?? null, // Added for stock validation
            'sizes'            => $sizes,
            'colors'           => $colors,
            'gallery'          => array_column($p['images'] ?? [], 'src'),
            'categories'       => array_column($p['categories'] ?? [], 'name'),
            'sku'              => $p['sku'] ?? '',
        ]);
    }

    public function getCategories(): array
    {
        return $this->cache->get('wc_categories', function() {
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
        }, self::CATEGORY_CACHE_TTL);
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
        return $this->cache->get('wc_product_' . $productId, function() use ($productId) {
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
        }, self::PRODUCT_CACHE_TTL);
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

    public function createOrder(
    array $data,
    array $cartItems,
    float $total,
    string $paymentMethod,
    ?int $customerId = null,
    ?string $customerToken = null
): array {
    $paymentMapping = [
        'cash_on_delivery' => 'cod',
        'clicktopay'       => 'clicktopay',
    ];
    $paymentMethodId = $paymentMapping[$paymentMethod] ?? 'cod';

    $billing = [
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'email'      => $data['email'],
        'phone'      => $data['phone'] ?? '',
        'address_1'  => $data['address'],
        'city'       => $data['city'],
        'postcode'   => $data['zip'] ?? '',
        'country'    => $data['country'] ?? 'TN',
    ];
    $shipping = $billing;

    $lineItems = [];
    foreach ($cartItems as $item) {
        $productId = (int) $item['product_id'];
        $wcProduct = $this->getProductById($productId); // fetch product data
        if (!$wcProduct) {
            throw new \RuntimeException(sprintf('Produit introuvable : %s', $item['name']));
        }

        $lineItem = [
            'product_id' => $productId,
            'quantity'   => (int) $item['qty'],
        ];

        $size  = $item['size'] ?? '';
        $color = $item['color'] ?? '';

        // Variable product: use variation_id
        if (($size || $color) && ($wcProduct['type'] ?? 'simple') === 'variable') {
            $variationId = $this->findVariationId($productId, $size, $color);
            if ($variationId) {
                $lineItem['variation_id'] = $variationId;
            } else {
                throw new \RuntimeException(sprintf(
                    'Variation introuvable pour « %s » (taille: %s, couleur: %s).',
                    $item['name'],
                    $size,
                    $color ?: 'aucune'
                ));
            }
        }
        // Simple product: send size/color as line item meta_data
        elseif ($size || $color) {
            $lineItem['meta_data'] = [];
            if ($size) {
                $lineItem['meta_data'][] = [
                    'key'   => 'Taille',
                    'value' => $size,
                ];
            }
            if ($color) {
                $lineItem['meta_data'][] = [
                    'key'   => 'Couleur',
                    'value' => $color,
                ];
            }
        }

        $lineItems[] = $lineItem;
    }

    $payload = [
        'customer_id'    => $customerId,
        'payment_method' => $paymentMethodId,
        'billing'        => $billing,
        'shipping'       => $shipping,
        'line_items'     => $lineItems,
        'customer_note'  => $data['notes'] ?? '',
    ];

    try {
        $response = $this->client->request(
            'POST',
            rtrim($this->wpUrl, '/') . '/wp-json/sevra/v1/create-order',
            [
                'headers' => [
                    'X-Sevra-Secret' => $this->wpApiSecret,
                    'Content-Type'   => 'application/json',
                ],
                'json'    => $payload,
                'timeout' => 15,
            ]
        );

        if ($response->getStatusCode() !== 201) {
            $body = $response->getContent(false);
            $this->logger->error('Custom order creation failed', ['status' => $response->getStatusCode(), 'body' => $body]);
            throw new \RuntimeException('La commande n\'a pas pu être créée. Veuillez réessayer.');
        }

        $order = $response->toArray();
        return ['id' => $order['order_id']];

    } catch (TransportExceptionInterface $e) {
        $this->logger->error('Custom order creation transport error', ['error' => $e->getMessage()]);
        throw new \RuntimeException('Service de commande indisponible.');
    }
}

    /**
     * Find variation ID by size and color attributes.
     *
     * The raw variations array is cached per product (keyed by product ID only).
     * The size/color matching is done after retrieval so the cache is reusable
     * across different size/color combinations for the same product.
     */
    private function findVariationId(int $productId, string $size, string $color): ?int
    {
        // Cache only the raw variations list, NOT the resolved variation ID.
        // This way different size/color combos for the same product all benefit
        // from the same cached API response.
        $variations = $this->cache->get('wc_variations_list_' . $productId, function() use ($productId) {
            try {
                $response = $this->client->request(
                    'GET',
                    rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/products/' . $productId . '/variations',
                    [
                        'auth_basic' => [$this->wcKey, $this->wcSecret],
                        'query'      => ['per_page' => 100],
                        'timeout'    => 10,
                    ]
                );

                if ($response->getStatusCode() !== 200) {
                    return [];
                }

                return $response->toArray();

            } catch (\Throwable $e) {
                $this->logger->error('Variation lookup error', [
                    'product_id' => $productId,
                    'error'      => $e->getMessage(),
                ]);
                return [];
            }
        }, self::PRODUCT_CACHE_TTL);

        // Match the correct variation from the cached list
        foreach ($variations as $variation) {
            $matches = true;
            foreach ($variation['attributes'] ?? [] as $attr) {
                $attrName  = strtolower($attr['name']   ?? '');
                $attrValue = strtolower($attr['option'] ?? '');

                if (str_contains($attrName, 'taille') || str_contains($attrName, 'size')) {
                    if ($size && strtolower($size) !== $attrValue) {
                        $matches = false;
                        break;
                    }
                } elseif (str_contains($attrName, 'couleur') || str_contains($attrName, 'color')) {
                    if ($color && strtolower($color) !== $attrValue) {
                        $matches = false;
                        break;
                    }
                }
            }
            if ($matches) {
                return (int) $variation['id'];
            }
        }

        return null;
    }

    // WooCommerceService.php
    public function getOrdersByCustomer(int $customerId): array
    {
        try {
            $response = $this->client->request('GET', rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/orders', [
                'auth_basic' => [$this->wcKey, $this->wcSecret],
                'query'      => [
                    'customer' => $customerId,
                    'per_page' => 50,
                    'order'    => 'desc',
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $orders = $response->toArray();

            // OPTIMIZATION: Batch fetch product data instead of N+1 calls
            $productIds = [];
            foreach ($orders as $order) {
                foreach ($order['line_items'] as $item) {
                    $productIds[] = $item['product_id'];
                }
            }
            $productIds = array_unique($productIds);

            $productImages = [];
            foreach ($productIds as $id) {
                $product = $this->getProductById($id);
                $productImages[$id] = $product['images'][0] ?? $product['gallery'][0] ?? null;
            }

            // Enrich with product images
            foreach ($orders as &$order) {
                foreach ($order['line_items'] as &$item) {
                    $productId = $item['product_id'] ?? 0;
                    $item['image'] = $productImages[$productId] ?? null;
                }
            }

            // Map to simpler structure for template
            return array_map(function ($o) {
                return [
                    'number' => $o['number'],
                    'date'   => $o['date_created'] ?? '',
                    'status' => $o['status'],
                    'total'  => $o['total'],
                    'items'  => array_map(function ($i) {
                        return [
                            'name'     => $i['name'],
                            'quantity' => $i['quantity'],
                            'price'    => $i['price'],
                            'image'    => $i['image'] ?? '/images/placeholder.jpg',
                        ];
                    }, $o['line_items']),
                ];
            }, $orders);
        } catch (\Throwable $e) {
            $this->logger->error('getOrdersByCustomer failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch a page of orders for a customer from WooCommerce API.
     * Returns an array with 'orders' and 'total_count' (if available).
     */
    public function getOrdersByCustomerPaginated(int $customerId, int $page = 1, int $perPage = 10): array
    {
        try {
            $response = $this->client->request('GET', rtrim($this->wpUrl, '/') . '/wp-json/wc/v3/orders', [
                'auth_basic' => [$this->wcKey, $this->wcSecret],
                'query'      => [
                    'customer' => $customerId,
                    'per_page' => $perPage,
                    'page'     => $page,
                    'order'    => 'desc',
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return ['orders' => [], 'total_count' => 0];
            }

            $totalCount = (int)($response->getHeaders()['x-wp-total'][0] ?? 0);
            $orders = $response->toArray();

            // OPTIMIZATION: Batch fetch product data
            $productIds = [];
            foreach ($orders as $order) {
                foreach ($order['line_items'] as $item) {
                    $productIds[] = $item['product_id'];
                }
            }
            $productIds = array_unique($productIds);

            $productImages = [];
            foreach ($productIds as $id) {
                $product = $this->getProductById($id);
                $productImages[$id] = $product['images'][0] ?? $product['gallery'][0] ?? null;
            }

            // Enrich with product images
            foreach ($orders as &$order) {
                foreach ($order['line_items'] as &$item) {
                    $productId = $item['product_id'] ?? 0;
                    $item['image'] = $productImages[$productId] ?? null;
                }
            }

            // Map to simpler structure
            $orders = array_map(function ($o) {
                return [
                    'number' => $o['number'],
                    'date'   =>  !empty($o['date_created']) ? $o['date_created'] : null,
                    'status' => $o['status'],
                    'total'  => $o['total'],
                    'items'  => array_map(function ($i) {
                        return [
                            'name'     => $i['name'],
                            'quantity' => $i['quantity'],
                            'price'    => $i['price'],
                            'image'    => $i['image'] ?? '/images/placeholder.jpg',
                        ];
                    }, $o['line_items']),
                ];
            }, $orders);

            return [
                'orders'      => $orders,
                'total_count' => $totalCount,
            ];

        } catch (\Throwable $e) {
            $this->logger->error('getOrdersByCustomerPaginated failed: ' . $e->getMessage());
            return ['orders' => [], 'total_count' => 0];
        }
    }
}
