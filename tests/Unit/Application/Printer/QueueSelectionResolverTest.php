<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Application\Printer;

use EasyPrint\Application\Printer\QueueSelectionResolver;
use EasyPrint\Application\Printer\SelectionPersistence;
use EasyPrint\Application\Printer\SelectionSource;
use EasyPrint\Domain\Printer\CupsConnectivity;
use EasyPrint\Domain\Printer\PrinterQueue;
use EasyPrint\Domain\Printer\PrinterState;
use EasyPrint\Domain\Printer\QueueSnapshot;
use PHPUnit\Framework\TestCase;

final class QueueSelectionResolverTest extends TestCase
{
    private QueueSelectionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new QueueSelectionResolver();
    }

    public function testAValidRequestWinsAndIsPersisted(): void
    {
        $selection = $this->resolver->resolve($this->snapshot(), 'SECONDARY', 'REFERENCE_QUEUE');

        self::assertSame('SECONDARY', $selection->queue?->identifier);
        self::assertSame(SelectionSource::Requested, $selection->source);
        self::assertSame(SelectionPersistence::Store, $selection->persistence);
    }

    public function testAValidPersistedSelectionSurvivesNavigation(): void
    {
        $selection = $this->resolver->resolve($this->snapshot(), null, 'SECONDARY');

        self::assertSame('SECONDARY', $selection->queue?->identifier);
        self::assertSame(SelectionSource::Persisted, $selection->source);
        self::assertSame(SelectionPersistence::Keep, $selection->persistence);
    }

    public function testAStaleSelectionFallsBackToTheCurrentDefault(): void
    {
        $selection = $this->resolver->resolve($this->snapshot(), 'REMOVED', 'ALSO_REMOVED');

        self::assertSame('REFERENCE_QUEUE', $selection->queue?->identifier);
        self::assertSame(SelectionSource::DefaultQueue, $selection->source);
        self::assertSame(SelectionPersistence::Store, $selection->persistence);
    }

    public function testTheFirstCurrentQueueIsUsedWhenThereIsNoValidDefault(): void
    {
        $snapshot = new QueueSnapshot(
            CupsConnectivity::Available,
            [new PrinterQueue('FIRST', PrinterState::Unknown)],
            'REMOVED_DEFAULT',
        );

        $selection = $this->resolver->resolve($snapshot, null, null);

        self::assertSame('FIRST', $selection->queue?->identifier);
        self::assertSame(SelectionSource::FirstAvailable, $selection->source);
    }

    public function testAStaleCookieIsClearedWhenNoQueuesRemain(): void
    {
        $selection = $this->resolver->resolve(
            new QueueSnapshot(CupsConnectivity::Available),
            null,
            'REMOVED',
        );

        self::assertNull($selection->queue);
        self::assertSame(SelectionSource::None, $selection->source);
        self::assertSame(SelectionPersistence::Clear, $selection->persistence);
    }

    private function snapshot(): QueueSnapshot
    {
        return new QueueSnapshot(
            connectivity: CupsConnectivity::Available,
            queues: [
                new PrinterQueue('REFERENCE_QUEUE', PrinterState::Ready),
                new PrinterQueue('SECONDARY', PrinterState::Stopped),
            ],
            defaultQueueIdentifier: 'REFERENCE_QUEUE',
        );
    }
}
