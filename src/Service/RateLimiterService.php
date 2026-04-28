<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RateLimiterService
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    /**
     * Check if action is rate limited and increment attempt counter
     */
    public function isLimited(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $cacheKey = 'rate_limit_' . md5($key);

        // Get current attempts
        $attempts = $this->cache->get($cacheKey, function (ItemInterface $item) use ($windowSeconds) {
            $item->expiresAfter($windowSeconds);
            return 0;
        });

        $limited = $attempts >= $maxAttempts;

        if (!$limited) {
            // Increment attempts
            $newAttempts = $attempts + 1;
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, function (ItemInterface $item) use ($newAttempts, $windowSeconds) {
                $item->expiresAfter($windowSeconds);
                return $newAttempts;
            });
        }

        $remaining = max(0, $maxAttempts - ($limited ? $attempts : $attempts + 1));
        $resetTime = time() + $windowSeconds;
        $retryAfter = $limited ? max(0, $resetTime - time()) : 0;

        return [
            'limited' => $limited,
            'remaining' => $remaining,
            'reset' => $resetTime,
            'retry_after' => $retryAfter
        ];
    }

    /**
     * Reset rate limit for a key (useful after successful action)
     */
    public function reset(string $key): void
    {
        $cacheKey = 'rate_limit_' . md5($key);
        $this->cache->delete($cacheKey);
    }

    /**
     * Get current attempt count for a key
     */
    public function getAttemptCount(string $key): int
    {
        $cacheKey = 'rate_limit_' . md5($key);
        return $this->cache->get($cacheKey, function () {
            return 0;
        });
    }
}
