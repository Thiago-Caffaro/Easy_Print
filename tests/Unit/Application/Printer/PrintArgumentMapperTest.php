<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Application\Printer;

use EasyPrint\Application\Printer\PrintArgumentMapper;
use EasyPrint\Application\Printer\PrintArgumentResult;
use EasyPrint\Application\Printer\PrintRequestFailure;
use EasyPrint\Application\Printer\PrintRequestInput;
use EasyPrint\Domain\Printer\CapabilityCategory;
use EasyPrint\Domain\Printer\CapabilityChoice;
use EasyPrint\Domain\Printer\CapabilityOption;
use EasyPrint\Domain\Printer\CapabilitySnapshot;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Domain\Printer\QueueSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrintArgumentMapperTest extends TestCase
{
    private const FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testItGeneratesDeterministicArgumentsFromTheActiveSnapshot(): void
    {
        $result = new PrintArgumentMapper()->map(
            $this->queues(),
            $this->capabilities(),
            new PrintRequestInput(
                queueIdentifier: 'REFERENCE_QUEUE',
                capabilityFingerprint: self::FINGERPRINT,
                copies: '2',
                pageRange: '1,3-5,16',
                options: [
                    'Duplex' => 'DuplexNoTumble',
                    'PageSize' => 'Letter',
                ],
            ),
        );

        self::assertTrue($result->succeeded());
        self::assertNull($result->failure);
        self::assertNotNull($result->validated);
        self::assertSame(2, $result->validated->copies);
        self::assertSame('1,3-5,16', $result->validated->pageRange);
        self::assertSame([
            'PageSize' => 'Letter',
            'Duplex' => 'DuplexNoTumble',
        ], $result->validated->selectedOptions);
        self::assertSame([
            '-d', 'REFERENCE_QUEUE',
            '-n', '2',
            '-P', '1,3-5,16',
            '-o', 'PageSize=Letter',
            '-o', 'Duplex=DuplexNoTumble',
        ], $result->validated->arguments);
    }

    public function testOptionalPageRangeAndOptionsCanBeOmitted(): void
    {
        $result = $this->map(new PrintRequestInput(
            'REFERENCE_QUEUE',
            self::FINGERPRINT,
            '1',
            '',
            [],
        ));

        self::assertNotNull($result->validated);
        self::assertSame(['-d', 'REFERENCE_QUEUE', '-n', '1'], $result->validated->arguments);
        self::assertNull($result->validated->pageRange);
    }

    #[DataProvider('invalidCopiesProvider')]
    public function testItRejectsCopiesOutsideTheStrictGrammar(string $copies): void
    {
        $result = $this->map(new PrintRequestInput(
            'REFERENCE_QUEUE',
            self::FINGERPRINT,
            $copies,
            null,
            [],
        ));

        self::assertSame(PrintRequestFailure::InvalidCopies, $result->failure);
        self::assertNull($result->validated);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function invalidCopiesProvider(): iterable
    {
        foreach (['', '0', '00', '01', '+1', '-1', '1 ', '1.0', '1e2', '1000', "1\n-o"] as $index => $copies) {
            yield 'invalid copies ' . $index => [$copies];
        }
    }

    #[DataProvider('invalidPageRangeProvider')]
    public function testItRejectsPageRangesOutsideTheStrictGrammar(string $pageRange): void
    {
        $result = $this->map(new PrintRequestInput(
            'REFERENCE_QUEUE',
            self::FINGERPRINT,
            '1',
            $pageRange,
            [],
        ));

        self::assertSame(PrintRequestFailure::InvalidPageRange, $result->failure);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function invalidPageRangeProvider(): iterable
    {
        foreach (['0', '01', '1-', '-2', '3-1', '1,', '1 2', '1-1000000', '1000000', '1;cancel'] as $range) {
            yield $range => [$range];
        }

        yield 'too many segments' => [implode(',', array_fill(0, 101, '1'))];
        yield 'too many bytes' => [str_repeat('1', 513)];
    }

    /** @param array<string,mixed> $options */
    #[DataProvider('invalidOptionsProvider')]
    public function testItRejectsUnadvertisedUnknownNonScalarAndUnsupportedOptions(array $options): void
    {
        $result = $this->map(new PrintRequestInput(
            'REFERENCE_QUEUE',
            self::FINGERPRINT,
            '1',
            null,
            $options,
        ));

        self::assertSame(PrintRequestFailure::InvalidOption, $result->failure);
        self::assertNull($result->validated);
    }

    /**
     * @return iterable<string,array{array<string,mixed>}>
     */
    public static function invalidOptionsProvider(): iterable
    {
        yield 'unadvertised name' => [['OutputBin' => 'Rear']];
        yield 'hidden command-shaped name' => [['-o' => 'PageSize=A4']];
        yield 'unsupported value' => [['PageSize' => 'Legal']];
        yield 'command-shaped value' => [['PageSize' => 'A4 -o Duplex=True']];
        yield 'array value' => [['PageSize' => ['A4']]];
        yield 'unknown driver option' => [['VendorSecret' => 'On']];
    }

    public function testQueueAndCapabilityChangesProduceSafeRetryFailures(): void
    {
        $missingQueue = new PrintArgumentMapper()->map(
            new QueueSnapshot(CupsConnectivity::Available),
            $this->capabilities(),
            new PrintRequestInput('REFERENCE_QUEUE', self::FINGERPRINT, '1', null, []),
        );
        $wrongQueueSnapshot = $this->map(new PrintRequestInput(
            'OTHER_QUEUE',
            self::FINGERPRINT,
            '1',
            null,
            [],
        ));
        $staleFingerprint = $this->map(new PrintRequestInput(
            'REFERENCE_QUEUE',
            str_repeat('b', 64),
            '1',
            null,
            [],
        ));

        self::assertSame(PrintRequestFailure::QueueChanged, $missingQueue->failure);
        self::assertTrue($missingQueue->failure->shouldRefreshForm());
        self::assertSame(PrintRequestFailure::QueueChanged, $wrongQueueSnapshot->failure);
        self::assertTrue($wrongQueueSnapshot->failure->shouldRefreshForm());
        self::assertSame(PrintRequestFailure::StaleCapabilities, $staleFingerprint->failure);
        self::assertTrue($staleFingerprint->failure->shouldRefreshForm());
    }

    public function testDependencyFailuresRemainDistinct(): void
    {
        $input = new PrintRequestInput('REFERENCE_QUEUE', self::FINGERPRINT, '1', null, []);
        $queueFailure = new PrintArgumentMapper()->map(
            QueueSnapshot::failed(CupsConnectivity::TimedOut),
            $this->capabilities(),
            $input,
        );
        $capabilityFailure = new PrintArgumentMapper()->map(
            $this->queues(),
            CapabilitySnapshot::failed('REFERENCE_QUEUE', CupsConnectivity::Unauthorized),
            $input,
        );

        self::assertSame(PrintRequestFailure::QueueUnavailable, $queueFailure->failure);
        self::assertFalse($queueFailure->failure->shouldRefreshForm());
        self::assertSame(PrintRequestFailure::CapabilitiesUnavailable, $capabilityFailure->failure);
        self::assertFalse($capabilityFailure->failure->shouldRefreshForm());
    }

    private function map(PrintRequestInput $input): PrintArgumentResult
    {
        return new PrintArgumentMapper()->map($this->queues(), $this->capabilities(), $input);
    }

    private function queues(): QueueSnapshot
    {
        return new QueueSnapshot(
            CupsConnectivity::Available,
            [new PrinterQueue('REFERENCE_QUEUE', PrinterState::Ready)],
            'REFERENCE_QUEUE',
        );
    }

    private function capabilities(): CapabilitySnapshot
    {
        return new CapabilitySnapshot(
            queueIdentifier: 'REFERENCE_QUEUE',
            connectivity: CupsConnectivity::Available,
            options: [
                new CapabilityOption(
                    'PageSize',
                    'Media Size',
                    CapabilityCategory::MediaSize,
                    [new CapabilityChoice('A4'), new CapabilityChoice('Letter')],
                    'A4',
                ),
                new CapabilityOption(
                    'Duplex',
                    'Two-Sided',
                    CapabilityCategory::Sides,
                    [new CapabilityChoice('None'), new CapabilityChoice('DuplexNoTumble')],
                    'None',
                ),
                new CapabilityOption(
                    'VendorSecret',
                    'Vendor Secret',
                    CapabilityCategory::Unknown,
                    [new CapabilityChoice('Off'), new CapabilityChoice('On')],
                    'Off',
                ),
            ],
            fingerprint: self::FINGERPRINT,
        );
    }
}
