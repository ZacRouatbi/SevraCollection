<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Psr\Log\LoggerInterface;

class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $resetService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function request(Request $request): Response
    {
        $email     = trim((string) $request->request->get('email', ''));
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        $isAjax    = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        $this->logger->info('Password reset request received', ['email' => $email, 'ip' => $request->getClientIp()]);

        if (!$this->isCsrfTokenValid('reset_password', $csrfToken)) {
            $message = 'Token de sécurité invalide.';
            if ($isAjax) return $this->json(['success' => false, 'message' => $message], Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $message);
            return $this->redirectToRoute('home', ['_fragment' => 'reset']);
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Veuillez fournir une adresse email valide.';
            if ($isAjax) return $this->json(['success' => false, 'message' => $message], Response::HTTP_BAD_REQUEST);
            $this->addFlash('error', $message);
            return $this->redirectToRoute('home', ['_fragment' => 'reset']);
        }

        try {

            $userExists = $this->resetService->checkUserExists($email);
            $message    = 'Si un compte existe avec cet email, vous recevrez un lien de réinitialisation.';

            if ($userExists) {
                $token = $this->resetService->generateResetToken($email);
                $this->resetService->sendResetEmail($email, $token);
                $this->logger->info('Password reset email sent', ['email' => $email]);
            } else {
                $this->logger->info('Reset requested for unknown email', ['email' => $email]);
            }

            if ($isAjax) return $this->json(['success' => true, 'message' => $message]);
            $this->addFlash('success', $message);
            return $this->redirectToRoute('home');

        } catch (\RuntimeException $e) {
            // Rate-limit or other expected errors — surface the message
            $this->logger->warning('Reset request failed: ' . $e->getMessage());
            if ($isAjax) return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_TOO_MANY_REQUESTS);
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('home', ['_fragment' => 'reset']);

        } catch (\Exception $e) {
            $this->logger->error('Reset request error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
            if ($isAjax) return $this->json(['success' => false, 'message' => $message], Response::HTTP_INTERNAL_SERVER_ERROR);
            $this->addFlash('error', $message);
            return $this->redirectToRoute('home', ['_fragment' => 'reset']);
        }
    }

    public function showResetForm(string $token): Response
    {
        $email = $this->resetService->validateResetToken($token);

        if (!$email) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('home');
        }

        return $this->render('pages/reset_password/reset_password.twig', ['token' => $token, 'email' => $email]);
    }

    public function update(Request $request): Response
    {
        $token           = (string) $request->request->get('token', '');
        $password        = (string) $request->request->get('password', '');
        $passwordConfirm = (string) $request->request->get('password_confirm', '');
        $csrfToken       = (string) $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('reset_password_update', $csrfToken)) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('reset_password_form', ['token' => $token]);
        }

        $email = $this->resetService->validateResetToken($token);
        if (!$email) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('home');
        }

        if ($password !== $passwordConfirm) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('reset_password_form', ['token' => $token]);
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$/', $password)) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.');
            return $this->redirectToRoute('reset_password_form', ['token' => $token]);
        }

        try {
            // Password update is now fully delegated to the service
            $success = $this->resetService->updateWordPressPassword($email, $password);

            if (!$success) {
                throw new \Exception('WP password update returned false');
            }

            $this->resetService->invalidateToken($token);
            $this->logger->info('Password updated successfully', ['email' => $email]);
            $this->addFlash('success', 'Votre mot de passe a été mis à jour. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('home', ['_fragment' => 'login']);

        } catch (\Exception $e) {
            $this->logger->error('Password update failed: ' . $e->getMessage());
            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour du mot de passe.');
            return $this->redirectToRoute('reset_password_form', ['token' => $token]);
        }
    }
}
