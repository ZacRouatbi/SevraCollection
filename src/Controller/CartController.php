<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CartService;
use App\Service\WooCommerceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    // ── Full cart page ────────────────────────────────────────────────────────

    #[Route('/cart', name: 'cart')]
    public function index(CartService $cart): Response
    {
        return $this->render('pages/cart/index.twig', [
            'cart_items' => $cart->getItems(),
            'subtotal'   => $cart->getSubtotal(),
            'count'      => $cart->getCount(),
        ]);
    }

    // ── Add to cart (AJAX POST, JSON body) ───────────────────────────────────

    #[Route('/cart/add', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request, CartService $cart, WooCommerceService $woo): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $productId = (int) ($data['product_id'] ?? 0);
        $qty       = max(1, (int) ($data['quantity'] ?? 1));
        $size      = trim((string) ($data['size']  ?? ''));
        $color     = trim((string) ($data['color'] ?? ''));

        if (!$productId) {
            return $this->json(['error' => 'Invalid product.'], 400);
        }

        // Fetch minimal product data from WooCommerce to store name/image/price
        $product = $woo->getProductById($productId);
        if (!$product) {
            return $this->json(['error' => 'Product not found.'], 404);
        }

        $cart->add(
            productId: $productId,
            name:      $product['name'],
            price:     $product['price'],
            image:     $product['images'][0] ?? '',
            qty:       $qty,
            size:      $size,
            color:     $color,
        );

        return $this->json([
            'success'  => true,
            'count'    => $cart->getCount(),
            'subtotal' => $cart->getSubtotal(),
            'items'    => $cart->getItems(),
        ]);
    }

    // ── Remove item (AJAX POST) ───────────────────────────────────────────────

    #[Route('/cart/remove', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request, CartService $cart): JsonResponse
    {
        $data    = json_decode($request->getContent(), true) ?? [];
        $itemKey = (string) ($data['item_key'] ?? '');

        if (!$itemKey) {
            return $this->json(['error' => 'Missing item key.'], 400);
        }

        $cart->remove($itemKey);

        return $this->json([
            'success'  => true,
            'count'    => $cart->getCount(),
            'subtotal' => $cart->getSubtotal(),
            'items'    => $cart->getItems(),
        ]);
    }

    // ── Update quantity (AJAX POST) ───────────────────────────────────────────

    #[Route('/cart/update', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request, CartService $cart): JsonResponse
    {
        $data    = json_decode($request->getContent(), true) ?? [];
        $itemKey = (string) ($data['item_key'] ?? '');
        $qty     = (int)    ($data['qty']      ?? 1);

        if (!$itemKey) {
            return $this->json(['error' => 'Missing item key.'], 400);
        }

        $cart->updateQty($itemKey, $qty);

        return $this->json([
            'success'  => true,
            'count'    => $cart->getCount(),
            'subtotal' => $cart->getSubtotal(),
            'items'    => $cart->getItems(),
        ]);
    }

    // ── Get cart state (AJAX GET — used on page load to hydrate drawer) ───────

    #[Route('/cart/state', name: 'cart_state', methods: ['GET'])]
    public function state(CartService $cart): JsonResponse
    {
        return $this->json([
            'count'    => $cart->getCount(),
            'subtotal' => $cart->getSubtotal(),
            'items'    => $cart->getItems(),
        ]);
    }
}
