<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Http\Security;

use EasyPrint\Http\Security\CsrfTokenManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CsrfTokenManagerTest extends TestCase
{
    public function testItRequiresACryptographicSigningSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CsrfTokenManager('too-short');
    }

    public function testItCreatesAndReusesAnAuthenticatedAnonymousSession(): void
    {
        $manager = new CsrfTokenManager(str_repeat('s', 32));

        $created = $manager->resolve(null);
        $resolved = $manager->resolve($created->cookie);

        self::assertTrue($created->new);
        self::assertFalse($resolved->new);
        self::assertSame($created->cookie, $resolved->cookie);
        self::assertSame($created->token, $resolved->token);
        self::assertTrue($manager->isValid($created->cookie, $created->token));
    }

    public function testItRejectsMissingTamperedAndCrossApplicationTokens(): void
    {
        $manager = new CsrfTokenManager(str_repeat('a', 32));
        $otherManager = new CsrfTokenManager(str_repeat('b', 32));
        $session = $manager->resolve(null);

        self::assertFalse($manager->isValid(null, $session->token));
        self::assertFalse($manager->isValid($session->cookie, null));
        self::assertFalse($manager->isValid($session->cookie . 'tampered', $session->token));
        self::assertFalse($manager->isValid($session->cookie, $session->token . 'tampered'));
        self::assertFalse($otherManager->isValid($session->cookie, $session->token));
    }
}
