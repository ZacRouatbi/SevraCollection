<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request, HttpClientInterface $client): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $firstName = $request->request->get('first_name');
        $lastName = $request->request->get('last_name');
        $csrfToken = $request->request->get('_csrf_token');

        // 1. Verify the CSRF token we added to your modal
        if (!$this->isCsrfTokenValid('register', $csrfToken)) {
            $this->addFlash('error', 'Sécurité : Jeton invalide.');
            return $this->redirectToRoute('home');
        }
       $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/';

        if (!preg_match($regex, $password)) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial.');
            return $this->redirectToRoute('home');
        }

        try {
            $response = $client->request('POST', $_ENV['WORDPRESS_URL'] . '/wp-json/wc/v3/customers/', [
                'auth_basic' => [$_ENV['WC_CONSUMER_KEY'], $_ENV['WC_CONSUMER_SECRET']],
                'json' => [
                    'email' => $email,
                    'password' => $password,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $email, // Using email as username is standard for decoupled setups
                ],
            ]);

            if ($response->getStatusCode() === 201) {
                $this->addFlash('success', 'Votre compte Sevra a été créé. Vous pouvez maintenant vous connecter.');
            } else {

                $data = $response->toArray(false);
                // Handle cases like "Email already exists"
                $this->addFlash('error', $data['message'] ?? 'Erreur lors de la création.');
            }
        } catch (\Exception $e) {

            $this->addFlash('error', 'Connexion au serveur impossible.');
        }

        return $this->redirectToRoute('home');
    }
}
