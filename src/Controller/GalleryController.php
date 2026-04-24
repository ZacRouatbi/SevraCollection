<?php

namespace App\Controller;

use App\Service\WooCommerceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GalleryController extends AbstractController
{
    #[Route('/new-arrivals', name: 'gallery')]
    public function index(WooCommerceService $woo): Response
    {
        $arrivals = $woo->getLatestProducts(8);

        return $this->render('pages/gallery/index.twig', [
            'arrivals' => $arrivals,
        ]);
    }
}
