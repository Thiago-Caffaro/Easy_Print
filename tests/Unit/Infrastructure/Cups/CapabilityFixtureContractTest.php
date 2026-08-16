<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Cups;

use EasyPrint\Domain\Printer\CapabilityOption;
use EasyPrint\Infrastructure\Cups\LpoptionsOutputParser;

use function file_get_contents;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CapabilityFixtureContractTest extends TestCase
{
    #[DataProvider('fixtureProvider')]
    public function testSyntheticCapabilityContracts(string $fixtureName): void
    {
        $contents = file_get_contents(
            dirname(__DIR__, 3) . '/Fixtures/Cups/Contract/capabilities/' . $fixtureName,
        );
        self::assertIsString($contents);
        $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        self::assertSame('synthetic-contract', $fixture['fixtureKind'] ?? null);
        self::assertIsArray($fixture['command'] ?? null);
        self::assertIsString($fixture['command']['stdout'] ?? null);
        self::assertIsArray($fixture['expected'] ?? null);
        self::assertIsArray($fixture['expected']['categories'] ?? null);
        self::assertIsInt($fixture['expected']['renderableCount'] ?? null);
        self::assertIsArray($fixture['expected']['unknownTechnicalIdentifiers'] ?? null);

        $options = new LpoptionsOutputParser()->parse($fixture['command']['stdout']);
        $renderable = array_values(array_filter(
            $options,
            static fn(CapabilityOption $option): bool => $option->isRenderable(),
        ));
        $unknown = array_values(array_filter(
            $options,
            static fn(CapabilityOption $option): bool => !$option->isRenderable(),
        ));

        self::assertSame(
            $fixture['expected']['categories'],
            array_map(static fn(CapabilityOption $option): string => $option->category->value, $options),
        );
        self::assertCount($fixture['expected']['renderableCount'], $renderable);
        self::assertSame(
            $fixture['expected']['unknownTechnicalIdentifiers'],
            array_map(static fn(CapabilityOption $option): string => $option->technicalIdentifier, $unknown),
        );
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'full option set' => ['full-option-set.json'];
        yield 'missing and driver-specific options' => ['missing-and-driver-specific-options.json'];
    }
}
