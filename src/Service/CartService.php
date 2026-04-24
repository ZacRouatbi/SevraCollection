<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Session-based cart.
 *
 * Cart structure in session (key: 'cart'):
 * [
 *   'abc123key' => [
 *     'key'        => 'abc123key',   // unique item key = md5(product_id + size + color)
 *     'product_id' => 42,
 *     'name'       => 'Ensemble Kaki',
 *     'image'      => 'https://...',
 *     'price'      => '89.00',
 *     'size'       => 'M',
 *     'color'      => 'Kaki',
 *     'qty'        => 2,
 *   ],
 *   ...
 * ]
 */
class CartService
{
    private const SESSION_KEY = 'sevra_cart';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function session(): \Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        return $this->requestStack->getSession();
    }

    private function all(): array
    {
        return $this->session()->get(self::SESSION_KEY, []);
    }

    private function save(array $cart): void
    {
        $this->session()->set(self::SESSION_KEY, $cart);
    }

    private static function itemKey(int|string $productId, string $size, string $color): string
    {
        return md5($productId . '|' . strtolower($size) . '|' . strtolower($color));
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Add or increment an item.
     */
    public function add(
        int|string $productId,
        string     $name,
        string     $price,
        string     $image,
        int        $qty   = 1,
        string     $size  = '',
        string     $color = '',
    ): void {
        $cart = $this->all();
        $key  = self::itemKey($productId, $size, $color);

        if (isset($cart[$key])) {
            $cart[$key]['qty'] = min($cart[$key]['qty'] + $qty, 10);
        } else {
            $cart[$key] = [
                'key'        => $key,
                'product_id' => $productId,
                'name'       => $name,
                'image'      => $image,
                'price'      => $price,
                'size'       => $size,
                'color'      => $color,
                'qty'        => $qty,
            ];
        }

        $this->save($cart);
    }

    /**
     * Remove an item by its key.
     */
    public function remove(string $itemKey): void
    {
        $cart = $this->all();
        unset($cart[$itemKey]);
        $this->save($cart);
    }

    /**
     * Update quantity of an item. Removes if qty <= 0.
     */
    public function updateQty(string $itemKey, int $qty): void
    {
        $cart = $this->all();
        if (!isset($cart[$itemKey])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$itemKey]);
        } else {
            $cart[$itemKey]['qty'] = min($qty, 10);
        }
        $this->save($cart);
    }

    /**
     * Return all items as a flat array (values only).
     */
    public function getItems(): array
    {
        return array_values($this->all());
    }

    /**
     * Total item count (sum of qtys).
     */
    public function getCount(): int
    {
        return array_sum(array_column($this->all(), 'qty'));
    }

    /**
     * Subtotal as a float.
     */
    public function getSubtotal(): float
    {
        $total = 0.0;
        foreach ($this->all() as $item) {
            $total += (float) $item['price'] * $item['qty'];
        }
        return round($total, 2);
    }

    /**
     * Wipe the cart.
     */
    public function clear(): void
    {
        $this->session()->remove(self::SESSION_KEY);
    }

    /**
     * Merge another cart (guest) INTO this cart without overwriting higher qtys.
     * Called after login to preserve guest items.
     */
    public function mergeFrom(array $guestItems): void
    {
        $cart = $this->all();
        foreach ($guestItems as $item) {
            $key = self::itemKey($item['product_id'], $item['size'] ?? '', $item['color'] ?? '');
            if (isset($cart[$key])) {
                // Keep whichever qty is higher
                $cart[$key]['qty'] = min(max($cart[$key]['qty'], $item['qty']), 10);
            } else {
                $cart[$key] = $item;
                $cart[$key]['key'] = $key;
            }
        }
        $this->save($cart);
    }
}
