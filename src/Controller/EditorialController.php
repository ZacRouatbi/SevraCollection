<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EditorialController extends AbstractController
{
    #[Route('/editoriaux', name: 'editorials')]
    public function index(): Response
    {
        // TODO: fetch editorials from WordPress custom post type via API
        // GET /wp-json/wp/v2/editorial
        return $this->render('pages/editorial/index.twig', [
            'editorials' => [],
        ]);
    }

    #[Route('/editoriaux/{slug}', name: 'editorial_show')]
    public function show(string $slug): Response
    {
        // TODO: fetch single editorial by slug from API
        // GET /wp-json/wp/v2/editorial?slug={slug}
        return $this->render('pages/editorial/show.twig', [
            'editorial' => null,
            'slug'      => $slug,
        ]);
    }
}
