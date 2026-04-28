<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AccountService;
use App\Service\CartService;
use App\Service\WooCommerceService;
use App\Service\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartService        $cartService,
        private readonly AccountService     $accountService,
        private readonly WooCommerceService $wooCommerce,
        private readonly RateLimiterService $rateLimiter  // ✅ Rate limiter added
    ) {}

    // ── Step 1: Checkout form ────────────────────────────────────────────────

    #[Route('/checkout', name: 'checkout')]
    public function index(Request $request): Response
    {
        $cartItems = $this->cartService->getItems();
        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('shop');
        }

        $user = [];
        if ($this->getUser()) {
            try {
                $wpUser  = $this->getAuthenticatedWpUser();
                $session = $request->getSession()->all();
                $user    = $this->accountService->getUserProfile($wpUser['id'], $session);
                foreach ($session as $k => $v) {
                    $request->getSession()->set($k, $v);
                }
            } catch (\Exception) {
                // Not fatal — just render with empty user data
            }
        }

        return $this->render('pages/checkout/index.twig', [
            'cart_items' => $cartItems,
            'subtotal'   => $this->cartService->getSubtotal(),
            'user'       => $user,
        ]);
    }

    // ── Step 2: Confirm & place order ────────────────────────────────────────

    #[Route('/checkout/confirm', name: 'checkout_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        $isAjax = $request->isXmlHttpRequest();

        // ✅ Rate limiting for checkout
        $clientIp = $request->getClientIp() ?? 'unknown';
        $rateKey = 'checkout_' . $clientIp;
        $rateCheck = $this->rateLimiter->isLimited($rateKey, 5, 300); // 5 attempts per 5 minutes

        if ($rateCheck['limited']) {
            if ($isAjax) {
                return $this->json([
                    'success' => false,
                    'error' => 'Trop de tentatives. Veuillez réessayer dans ' . $rateCheck['retry_after'] . ' secondes.'
                ], 429);
            }
            $this->addFlash('error', 'Trop de tentatives. Veuillez réessayer plus tard.');
            return $this->redirectToRoute('checkout');
        }

        // ── CSRF ──────────────────────────────────────────────────
        if (!$this->isCsrfTokenValid('checkout', $request->request->get('_csrf_token', ''))) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Jeton CSRF invalide.'], 403);
            }
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // ── Cart must not be empty ────────────────────────────────
        $cartItems = $this->cartService->getItems();
        if (empty($cartItems)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => 'Votre panier est vide.'], 400);
            }
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('shop');
        }

        // ── Validate fields ───────────────────────────────────────
        $fields = [
            'first_name' => ['label' => 'Prénom',    'min' => 2,  'required' => true],
            'last_name'  => ['label' => 'Nom',        'min' => 2,  'required' => true],
            'email'      => ['label' => 'E-mail',     'min' => 6,  'required' => true, 'type' => 'email'],
            'phone'      => ['label' => 'Téléphone',  'min' => 7,  'required' => false, 'pattern' => '/^\+?[0-9\s\-]{7,15}$/'],
            'address'    => ['label' => 'Adresse',    'min' => 5,  'required' => true],
            'city'       => ['label' => 'Ville',      'min' => 2,  'required' => true],
            'zip'        => ['label' => 'Code postal', 'min' => 2, 'required' => false, 'pattern' => '/^[A-Za-z0-9\-\s]{2,10}$/'],
        ];

        $data   = [];
        $errors = [];

        foreach ($fields as $field => $rules) {
            $val          = trim($request->request->get($field, ''));
            $data[$field] = $val;

            if ($rules['required'] && $val === '') {
                $errors[] = sprintf('Le champ "%s" est obligatoire.', $rules['label']);
                continue;
            }
            if ($val !== '' && strlen($val) < $rules['min']) {
                $errors[] = sprintf('Le champ "%s" doit comporter au moins %d caractères.', $rules['label'], $rules['min']);
            }
            if (!empty($rules['pattern']) && $val !== '' && !preg_match($rules['pattern'], $val)) {
                $errors[] = sprintf('Le format du champ "%s" est invalide.', $rules['label']);
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse email invalide.';
        }

        if (!empty($errors)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'errors' => $errors], 400);
            }
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->render('pages/checkout/index.twig', [
                'cart_items' => $cartItems,
                'subtotal'   => $this->cartService->getSubtotal(),
                'user'       => $data,
            ]);
        }

        // ── Price and stock integrity check ───────────────────────
        $expectedTotal = '0.00';
        $wcErrors      = [];

        foreach ($cartItems as $item) {
            $productId = (int) $item['product_id'];
            $wcProduct = $this->wooCommerce->getProductById($productId);

            if (!$wcProduct) {
                $wcErrors[] = sprintf('Le produit « %s » n\'est plus disponible.', $item['name']);
                continue;
            }

            // ── Stock validation ──────────────────────────────────
            $requestedQty = (int) $item['qty'];
            $stockStatus = $wcProduct['stock_status'] ?? 'instock';
            $stockQuantity = $wcProduct['stock_quantity'] ?? null;

            if ($stockStatus === 'outofstock') {
                $wcErrors[] = sprintf('Le produit « %s » n\'est plus en stock.', $item['name']);
                continue;
            }

            if ($stockQuantity !== null && $requestedQty > $stockQuantity) {
                $wcErrors[] = sprintf(
                    'Stock insuffisant pour « %s ». Disponible : %d, demandé : %d.',
                    $item['name'],
                    $stockQuantity,
                    $requestedQty
                );
                continue;
            }

            // ── Price validation using active price from WC ────────
            $currentPrice = (string) ($wcProduct['price'] ?? '0');
            $cartPrice    = (string) $item['price'];

            $currentPriceNorm = bcadd($currentPrice, '0', 2);
            $cartPriceNorm    = bcadd($cartPrice, '0', 2);

            if (bccomp($cartPriceNorm, $currentPriceNorm, 2) !== 0) {
                $wcErrors[] = sprintf(
                    'Le prix de « %s » a changé (panier : %s DT, actuel : %s DT). Veuillez revoir votre panier.',
                    $item['name'],
                    number_format((float)$cartPrice, 2),
                    number_format((float)$currentPrice, 2)
                );
                continue;
            }

            $expectedTotal = bcadd($expectedTotal, bcmul($currentPriceNorm, (string)$requestedQty, 2), 2);
        }

        if (!empty($wcErrors)) {
            if ($isAjax) {
                return $this->json(['success' => false, 'errors' => $wcErrors], 400);
            }
            foreach ($wcErrors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('checkout');
        }

        // ── Resolve customer ──────────────────────────────────────
        $customerId    = null;
        $customerToken = null;
        if ($this->getUser()) {
            try {
                $wpUser        = $this->getAuthenticatedWpUser();
                $customerId    = $wpUser['id'];
                $userObj       = $this->getUser();
                $customerToken = method_exists($userObj, 'getToken') ? $userObj->getToken() : null;
            } catch (\Exception) {
                // Guest fallback — order will be created without a customer ID
            }
        }

        $paymentMethod = (string) $request->request->get('payment_method', 'cash_on_delivery');

        // ── Create WooCommerce order ──────────────────────────────
        try {
            $order = $this->wooCommerce->createOrder(
                $data,
                $cartItems,
                (float) $expectedTotal,
                $paymentMethod,
                $customerId,
                $customerToken
            );
        } catch (\RuntimeException $e) {
            if ($isAjax) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('checkout');
        }

        $this->cartService->clear();
        return $this->json([
            'success'  => true,
            'order_id' => $order['id'],
        ]);
    }

    #[Route('/commande/confirmation/{id}', name: 'order_confirmation')]
    public function confirmation(int $id): Response
    {
        return $this->render('pages/checkout/confirmation.twig', [
            'order_id' => $id,
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function getAuthenticatedWpUser(): array
    {
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedException('Not logged in');
        }
        if (method_exists($user, 'getWpUser')) {
            return $user->getWpUser();
        }
        return [
            'id'    => method_exists($user, 'getWpId')    ? $user->getWpId()    : 0,
            'email' => method_exists($user, 'getEmail')   ? $user->getEmail()   : '',
        ];
    }

}
