<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AccountService;
use App\Service\CartService;
use App\Service\WooCommerceService;
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
        private readonly WooCommerceService $wooCommerce
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
                // Persist any session values the service may have written back
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
        // ── CSRF ──────────────────────────────────────────────────
        if (!$this->isCsrfTokenValid('checkout', $request->request->get('_csrf_token', ''))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // ── Cart must not be empty ────────────────────────────────
        $cartItems = $this->cartService->getItems();
        if (empty($cartItems)) {
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
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            // Re-render form with user-supplied values so fields aren't wiped
            return $this->render('pages/checkout/index.twig', [
                'cart_items' => $cartItems,
                'subtotal'   => $this->cartService->getSubtotal(),
                'user'       => $data,
            ]);
        }

        // ── Price integrity check ─────────────────────────────────
        $expectedTotal = 0.0;
        $wcErrors      = [];

        foreach ($cartItems as $item) {
            $productId = (int) $item['product_id'];
            $wcProduct = $this->wooCommerce->getProductById($productId);

            if (!$wcProduct) {
                $wcErrors[] = sprintf('Le produit « %s » n\'est plus disponible.', $item['name']);
                continue;
            }

            $currentPrice = (float) ($wcProduct['sale_price'] ?: $wcProduct['regular_price'] ?: $wcProduct['price'] ?? 0);
            $cartPrice    = (float) $item['price'];

            if (abs($cartPrice - $currentPrice) > 0.001) {
                $wcErrors[] = sprintf(
                    'Le prix de « %s » a changé (panier : %s DT, actuel : %s DT). Veuillez revoir votre panier.',
                    $item['name'],
                    number_format($cartPrice, 2),
                    number_format($currentPrice, 2)
                );
            }

            $expectedTotal += $currentPrice * (int) $item['qty'];
        }

        if (!empty($wcErrors)) {
            foreach ($wcErrors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('checkout');
        }

        $expectedTotal  = round($expectedTotal, 2);
        $submittedTotal = (float) $request->request->get('order_total', 0);

        if (abs($submittedTotal - $expectedTotal) > 0.001) {
            $this->addFlash('error', 'Le total de la commande a été modifié. Veuillez réessayer.');
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
                $expectedTotal,
                $paymentMethod,
                $customerId,
                $customerToken
            );
        } catch (\RuntimeException $e) {
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
    // Optional: fetch the order from WooCommerce to display details
    // $order = $this->wooCommerce->getOrderById($id);

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
