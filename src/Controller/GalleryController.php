<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GalleryController extends AbstractController
{
    #[Route('/new-arrivals', name: 'gallery')]
    public function index(): Response
    {
        // TODO: fetch new arrivals from API
        return $this->render('pages/gallery/index.twig', [
            'arrivals' => [],
        ]);
    }
}
