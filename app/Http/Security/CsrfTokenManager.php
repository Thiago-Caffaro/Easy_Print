<?php

declare(strict_types=1);

namespace EasyPrint\Http\Security;

use function base64_decode;
use function base64_encode;
use function count;
use function explode;
use function hash_equals;
use function hash_hmac;

use InvalidArgumentException;

use function is_string;

use LogicException;

use function random_bytes;
use function str_repeat;
use function str_replace;
use function strlen;
use function strtr;

final readonly class CsrfTokenManager
{
    private const SESSION_BYTES = 32;

    public function __construct(private string $secret)
    {
        if (32 > strlen($secret)) {
            throw new InvalidArgumentException('The CSRF signing secret must contain at least 32 bytes.');
        }
    }

    public function resolve(?string $cookie): CsrfSession
    {
        $sessionId = $this->validSessionId($cookie);
        $new = null === $sessionId;

        if ($new) {
            $sessionId = random_bytes(self::SESSION_BYTES);
            $cookie = $this->encode($sessionId) . '.' . $this->signature('session', $sessionId);
        }

        if (!is_string($cookie)) {
            throw new LogicException('A resolved CSRF session is missing its cookie.');
        }

        return new CsrfSession(
            cookie: $cookie,
            token: $this->signature('csrf', $sessionId),
            new: $new,
        );
    }

    public function isValid(?string $cookie, ?string $token): bool
    {
        if (!is_string($token) || '' === $token) {
            return false;
        }

        $sessionId = $this->validSessionId($cookie);

        return null !== $sessionId && hash_equals($this->signature('csrf', $sessionId), $token);
    }

    private function validSessionId(?string $cookie): ?string
    {
        if (!is_string($cookie)) {
            return null;
        }

        $parts = explode('.', $cookie);

        if (2 !== count($parts)) {
            return null;
        }

        $sessionId = $this->decode($parts[0]);

        if (null === $sessionId || self::SESSION_BYTES !== strlen($sessionId)) {
            return null;
        }

        return hash_equals($this->signature('session', $sessionId), $parts[1]) ? $sessionId : null;
    }

    private function signature(string $purpose, string $sessionId): string
    {
        return $this->encode(hash_hmac('sha256', $purpose . "\0" . $sessionId, $this->secret, true));
    }

    private function encode(string $value): string
    {
        return str_replace('=', '', strtr(base64_encode($value), '+/', '-_'));
    }

    private function decode(string $value): ?string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);

        return false === $decoded ? null : $decoded;
    }
}
