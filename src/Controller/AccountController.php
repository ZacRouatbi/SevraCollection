<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Psr\Log\LoggerInterface;

class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly LoggerInterface $logger,
    ) {}

    public function index(Request $request): Response
    {
        $wpUser = $this->getAuthenticatedWpUser();

        $session  = $request->getSession();
        $bag      = $session->all();

        $profile = $this->accountService->getUserProfile($wpUser['id'], $bag);
        $orders  = $this->accountService->getUserOrders($wpUser['id'],  $bag);

        foreach ($bag as $k => $v) {
            $session->set($k, $v);
        }

        return $this->render('pages/account/index.twig', [
            'user'   => $profile,
            'orders' => $orders,
        ]);
    }

    public function update(Request $request): Response
    {
        $wpUser = $this->getAuthenticatedWpUser();

        // ✅ GET JWT TOKEN
        $userObj = $this->getUser();
        $token = method_exists($userObj, 'getToken') ? $userObj->getToken() : null;

        if (!$this->isCsrfTokenValid('account_update', $request->request->get('_csrf_token', ''))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('account');
        }

        $raw = $request->request->all();

        ['data' => $profileData, 'errors' => $profileErrors] =
            $this->accountService->validateProfileData($raw);

        ['valid' => $pwValid, 'errors' => $pwErrors, 'skip' => $skipPassword] =
            $this->accountService->validatePasswordChange($raw);

        $allErrors = array_merge($profileErrors, $pwErrors);

        if (!empty($allErrors)) {
            foreach ($allErrors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('account');
        }

        $session = $request->getSession();
        $bag     = $session->all();
        $anyFailed = false;

        // ✅ PASS TOKEN
        $profileResult = $this->accountService->updateProfile(
            $wpUser['id'],
            $profileData,
            $bag,
            $token
        );

        if (!$profileResult['success']) {
            foreach ($profileResult['errors'] as $error) {
                $this->addFlash('error', $error);
            }
            $anyFailed = true;
        }

        if (!$skipPassword && $pwValid) {
            $pwResult = $this->accountService->updatePassword(
                $wpUser['id'],
                $raw['current_password'],
                $raw['new_password'],
                $token
            );

            if (!$pwResult['success']) {
                $this->addFlash('error', $pwResult['message']);
                $anyFailed = true;
            }
        }

        foreach ($bag as $k => $v) {
            $session->set($k, $v);
        }

        if (!$anyFailed) {
            $this->addFlash('success', 'Vos informations ont été mises à jour.');
        }

        return $this->redirectToRoute('account');
    }

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
            'id'    => method_exists($user, 'getWpId') ? $user->getWpId() : 0,
            'email' => method_exists($user, 'getEmail') ? $user->getEmail() : '',
        ];
    }
}
