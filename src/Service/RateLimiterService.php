<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class RateLimiterService
{
    private const WINDOW_SIZE = 60; // 60 seconds

    public function __construct(
        private readonly CacheInterface $cache
    ) {}

    /**
     * Check if action is allowed and record the attempt
     *
     * @param string $key Unique identifier for the action (e.g., 'login_' . $ip)
     * @param int $maxAttempts Maximum attempts allowed in the time window
     * @param int $window Window size in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function checkAndRecord(string $key, int $maxAttempts, int $window = self::WINDOW_SIZE): array
    {
        $cacheKey = 'rate_limit_' . md5($key);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($maxAttempts, $window) {
            $item->expiresAfter($window);
            return [
                'attempts' => 0,
                'reset_time' => time() + $window
            ];
        });
    }

    /**
     * Check if the action is rate limited
     */
    public function isLimited(string $key, int $maxAttempts, int $window = self::WINDOW_SIZE): array
    {
        $cacheKey = 'rate_limit_' . md5($key);
        $data = $this->cache->get($cacheKey, function () { return null; });

        if ($data === null) {
            return ['limited' => false, 'remaining' => $maxAttempts, 'reset' => time() + $window];
        }

        $attempts = $data['attempts'] + 1;
        $remaining = max(0, $maxAttempts - $attempts);
        $limited = $attempts > $maxAttempts;

        // Update the cache
        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($attempts, $window, $data) {
            $item->expiresAfter($window);
            return [
                'attempts' => $attempts,
                'reset_time' => $data['reset_time'] ?? time() + $window
            ];
        });

        return [
            'limited' => $limited,
            'remaining' => $remaining,
            'reset' => $data['reset_time'] ?? time() + $window,
            'retry_after' => $limited ? ($data['reset_time'] ?? time() + $window) - time() : 0
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
}
