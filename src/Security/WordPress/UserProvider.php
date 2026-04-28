<?php

declare(strict_types=1);

namespace App\Security\WordPress;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class UserProvider implements UserProviderInterface
{
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        // If the JWT token has expired, force re-login cleanly
        $token  = $user->getToken();
        $parts  = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(
                base64_decode(str_pad($parts[1], strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)),
                true
            );
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                throw new UnsupportedUserException('JWT token expired, please log in again.');
            }
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new \Exception('Authentication is handled entirely by the WordPressAuthenticator.');
    }
}
