<?php

declare(strict_types=1);

namespace App\Auth;

final class AuthSession
{
    private const KEY = '_authentication';

    public function __construct(
        private readonly SessionManager $sessions,
        private readonly int $idleTimeout,
        private readonly int $absoluteTimeout,
    ) {
        if ($idleTimeout < 60 || $absoluteTimeout < $idleTimeout) {
            throw new \InvalidArgumentException('Invalid session timeout configuration.');
        }
    }

    public function login(int $userId, ?int $now = null): void
    {
        $this->sessions->regenerate();
        $time = $now ?? time();
        $_SESSION[self::KEY] = [
            'user_id' => $userId,
            'authenticated_at' => $time,
            'last_activity_at' => $time,
        ];
    }

    public function logout(): void
    {
        unset($_SESSION[self::KEY]);
        $this->sessions->regenerate();
    }

    public function userId(?int $now = null): ?int
    {
        $state = $_SESSION[self::KEY] ?? null;
        if (!is_array($state)) {
            return null;
        }
        $userId = filter_var($state['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $authenticatedAt = filter_var($state['authenticated_at'] ?? null, FILTER_VALIDATE_INT);
        $lastActivityAt = filter_var($state['last_activity_at'] ?? null, FILTER_VALIDATE_INT);
        if ($userId === false || $authenticatedAt === false || $lastActivityAt === false) {
            $this->logout();
            return null;
        }
        $time = $now ?? time();
        if ($time - $lastActivityAt > $this->idleTimeout || $time - $authenticatedAt > $this->absoluteTimeout) {
            $this->logout();
            return null;
        }
        $_SESSION[self::KEY]['last_activity_at'] = $time;
        return (int) $userId;
    }

    public function hasExpired(?int $now = null): bool
    {
        $hadAuthentication = isset($_SESSION[self::KEY]);
        return $hadAuthentication && $this->userId($now) === null;
    }
}
