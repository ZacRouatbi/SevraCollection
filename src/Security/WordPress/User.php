<?php

declare(strict_types=1);

namespace App\Security\WordPress;

use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    public function __construct(
        private readonly string $email,
        private readonly string $token,
        private readonly int    $wpUserId,
        private readonly array $roles = []
    ) {}

    public function getRoles(): array
    {
        $roles = $this->roles;
        // Guarantee every logged-in user has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // No local sensitive data to clear
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }
     public function getWpId(): int
    {
        return $this->wpUserId;
    }
     public function getWpUser(): array
    {
        return [
            'id'    => $this->wpUserId,
            'email' => $this->email,
            'first_name' => '',
            'last_name'  => '',
        ];
    }
}
