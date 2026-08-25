<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Unit\Infrastructure\Cups;

use EasyPrint\Domain\Printer\CapabilityCategory;
use EasyPrint\Infrastructure\Cups\LpoptionsOutputParser;
use EasyPrint\Infrastructure\Cups\MalformedLpoptionsOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LpoptionsOutputParserTest extends TestCase
{
    public function testItSeparatesTechnicalIdentifiersDefaultsCategoriesAndDriverLabels(): void
    {
        $parser = new LpoptionsOutputParser();
        $options = $parser->parse(<<<'OUTPUT'
            PageSize/Media Size: *A4 Letter
            MediaType/Media Type: *Plain Glossy
            ColorModel/Color Mode: RGB *Gray
            PrintoutMode/Print Quality: Draft *Normal High
            Resolution/Resolution: *300dpi 600x600dpi
            orientation-requested/Orientation: *portrait landscape
            Duplex/Two-Sided: *None DuplexNoTumble DuplexTumble
            InputSlot/Media Source: *Auto Rear
            print-scaling/Scaling: *auto fit fill none
            VendorSecret/Vendor Diagnostic: *Off On
            OUTPUT);

        self::assertSame([
            CapabilityCategory::MediaSize,
            CapabilityCategory::MediaType,
            CapabilityCategory::ColorMode,
            CapabilityCategory::Quality,
            CapabilityCategory::Resolution,
            CapabilityCategory::Orientation,
            CapabilityCategory::Sides,
            CapabilityCategory::MediaSource,
            CapabilityCategory::Scaling,
            CapabilityCategory::Unknown,
        ], array_column($options, 'category'));
        self::assertSame('PageSize', $options[0]->technicalIdentifier);
        self::assertSame('Media Size', $options[0]->driverLabel);
        self::assertSame('A4', $options[0]->defaultTechnicalIdentifier);
        self::assertSame(['A4', 'Letter'], array_column($options[0]->choices, 'technicalIdentifier'));
        self::assertTrue($options[0]->isRenderable());
        self::assertFalse($options[9]->isRenderable());
        self::assertCount(9, array_filter($options, static fn($option): bool => $option->isRenderable()));
    }

    public function testMissingCategoriesRemainAbsentAndUnknownOptionsRemainDiagnosable(): void
    {
        $parser = new LpoptionsOutputParser();
        $options = $parser->parse("PageSize/Page Size: *A4\nStaplify/Vendor Finisher: *False True\n");

        self::assertCount(2, $options);
        self::assertSame(CapabilityCategory::MediaSize, $options[0]->category);
        self::assertSame(CapabilityCategory::Unknown, $options[1]->category);
        self::assertSame('Staplify', $options[1]->technicalIdentifier);
        self::assertSame('Vendor Finisher', $options[1]->driverLabel);
    }

    public function testItAcceptsSignedNumericDriverChoices(): void
    {
        $options = new LpoptionsOutputParser()->parse(
            'Brightness/Brightness: -25 *0 25' . "\n" .
            'Contrast/Contrast: -25 *0 25',
        );

        self::assertSame(['-25', '0', '25'], array_column($options[0]->choices, 'technicalIdentifier'));
        self::assertSame('0', $options[0]->defaultTechnicalIdentifier);
        self::assertSame(['-25', '0', '25'], array_column($options[1]->choices, 'technicalIdentifier'));
    }

    public function testItPreservesAllAdvertisedMediaTypeChoices(): void
    {
        $options = new LpoptionsOutputParser()->parse(
            'MediaType/Print Quality: PLAIN_HIGH *PLAIN_NORMAL PMMATT_HIGH PMMATT_NORMAL '
            . 'PLATINA_HIGH PLATINA_NORMAL PMPHOTO_HIGH PMPHOTO_NORMAL PMPHOTO_DRAFT '
            . 'PSGLOS_HIGH PSGLOS_NORMAL PSGLOS_DRAFT LCPP_HIGH LCPP_NORMAL LCPP_DRAFT '
            . 'ENV_HIGH ENV_NORMAL',
        );

        self::assertCount(1, $options);
        self::assertSame(CapabilityCategory::MediaType, $options[0]->category);
        self::assertCount(17, $options[0]->choices);
        self::assertSame('PLAIN_NORMAL', $options[0]->defaultTechnicalIdentifier);
        self::assertContains('PMPHOTO_HIGH', array_column($options[0]->choices, 'technicalIdentifier'));
        self::assertContains('PSGLOS_DRAFT', array_column($options[0]->choices, 'technicalIdentifier'));
        self::assertContains('LCPP_NORMAL', array_column($options[0]->choices, 'technicalIdentifier'));
    }

    public function testTheFingerprintChangesWithDriverAdvertisedData(): void
    {
        $parser = new LpoptionsOutputParser();
        $first = $parser->parse('PageSize/Page Size: *A4 Letter');
        $same = $parser->parse('PageSize/Page Size: *A4 Letter');
        $changed = $parser->parse('PageSize/Page Size: A4 *Letter');

        self::assertSame($parser->fingerprint($first), $parser->fingerprint($same));
        self::assertNotSame($parser->fingerprint($first), $parser->fingerprint($changed));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $parser->fingerprint($first));
    }

    #[DataProvider('malformedOutputProvider')]
    public function testItRejectsMalformedOrAmbiguousOutput(string $output): void
    {
        $this->expectException(MalformedLpoptionsOutput::class);

        new LpoptionsOutputParser()->parse($output);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function malformedOutputProvider(): iterable
    {
        yield 'invalid line' => ['not an option'];
        yield 'empty choices' => ['PageSize/Page Size:'];
        yield 'duplicate option' => ["PageSize/Page Size: *A4\nPageSize/Page Size: *Letter"];
        yield 'duplicate choice' => ['PageSize/Page Size: *A4 A4'];
        yield 'multiple defaults' => ['PageSize/Page Size: *A4 *Letter'];
        yield 'unsafe option identifier' => ['-o/Injected: *A4'];
        yield 'unsafe choice identifier' => ['PageSize/Page Size: *A4 option=value'];
        yield 'control character in label' => ["PageSize/Page\tSize: *A4"];
        yield 'invalid label encoding' => ["PageSize/Page\xFFSize: *A4"];
        yield 'null byte' => ["PageSize/Page Size: *A4\0Letter"];
    }
}
