<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\WooCommerceService;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(WooCommerceService $woo): Response
    {
        $session = $this->container->get('request_stack')->getCurrentRequest()->getSession();
        $error = $session->get('_security.last_error');
        $lastUsername = $session->get('_security.last_username');

        // Clear them from session after retrieving
        if ($error) {
            $session->remove('_security.last_error');
            $session->remove('_security.last_username');
        }

        return $this->render('pages/home/index.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
            'show_login_modal' => $error !== null,
            'latest_arrivals'   => $woo->getLatestProducts(3),
            'featured_products'   => $woo->getLatestProducts(4),
        ]);
    }
}
