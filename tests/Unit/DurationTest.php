<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Duration;
use Kaveraa\DataLifecycle\Exception\InvalidDuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function durations(): iterable
    {
        yield 'years' => ['3 years', '2026-09-28', '2029-09-28'];
        yield 'one year' => ['1 year', '2026-09-28', '2027-09-28'];
        yield 'months' => ['6 months', '2026-09-28', '2027-03-28'];
        yield 'weeks' => ['2 weeks', '2026-09-28', '2026-10-12'];
        yield 'days' => ['30 days', '2026-09-28', '2026-10-28'];
        yield 'hours' => ['48 hours', '2026-09-28 00:00:00', '2026-09-30 00:00:00'];
        yield 'minutes' => ['90 minutes', '2026-09-28 00:00:00', '2026-09-28 01:30:00'];
        yield 'seconds' => ['30 seconds', '2026-09-28 00:00:00', '2026-09-28 00:00:30'];
        yield 'iso days' => ['P30D', '2026-09-28', '2026-10-28'];
        yield 'iso years' => ['P3Y', '2026-09-28', '2029-09-28'];
        yield 'iso hours' => ['PT12H', '2026-09-28 00:00:00', '2026-09-28 12:00:00'];
        yield 'spaces' => ['  3   years  ', '2026-09-28', '2029-09-28'];
        yield 'singular unit' => ['2 day', '2026-09-28', '2026-09-30'];
    }

    #[DataProvider('durations')]
    public function test_it_adds_a_duration_to_a_date(string $written, string $from, string $expected): void
    {
        $result = Duration::parse($written)->add(new DateTimeImmutable($from));

        self::assertSame((new DateTimeImmutable($expected))->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    public function test_it_goes_back_in_time(): void
    {
        $result = Duration::parse('3 years')->sub(new DateTimeImmutable('2026-09-28'));

        self::assertSame('2023-09-28', $result->format('Y-m-d'));
    }

    public function test_it_reads_a_duration_already_parsed(): void
    {
        $duration = Duration::parse('3 years');

        self::assertSame($duration, Duration::parse($duration));
    }

    public function test_it_ranks_durations(): void
    {
        self::assertGreaterThan(
            Duration::parse('30 days')->approximateSeconds(),
            Duration::parse('2 months')->approximateSeconds(),
        );
    }

    public function test_it_writes_itself_back(): void
    {
        self::assertSame('3 years', (string) Duration::parse('3 years'));
        self::assertSame('1 day', (string) Duration::parse('1 day'));
    }

    public function test_zero_is_allowed(): void
    {
        self::assertTrue(Duration::parse('0 days')->isZero());
    }

    public function test_it_refuses_an_empty_duration(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::parse('   ');
    }

    public function test_it_refuses_a_duration_it_cannot_read(): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('Cannot read the duration "soon"');

        Duration::parse('soon');
    }

    public function test_it_refuses_an_unknown_unit(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::of(3, 'fortnights');
    }

    public function test_it_refuses_a_negative_duration(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::of(-1, 'day');
    }
}
