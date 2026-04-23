<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorController extends AbstractController
{
    public function show(Throwable $exception): Response
    {
        $statusCode = 500;

        // Get the status code from the exception
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
        } elseif ($exception->getCode() !== 0) {
            $statusCode = $exception->getCode();
        }

        // Return the appropriate error template based on status code
        if ($statusCode === 404) {
            return $this->render('bundles/TwigBundle/Exception/error404.twig')
                ->setStatusCode(404);
        }

        // For other errors, you can create a generic template
        return $this->render('bundles/TwigBundle/Exception/error.twig', [
            'status_code' => $statusCode,
            'status_text' => 'Erreur',
            'exception' => $exception,
        ])->setStatusCode($statusCode);
    }
}
