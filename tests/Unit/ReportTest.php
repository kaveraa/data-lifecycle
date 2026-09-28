<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use Kaveraa\DataLifecycle\Duration;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Report;
use Kaveraa\DataLifecycle\Step;
use Kaveraa\DataLifecycle\Subject;
use Kaveraa\DataLifecycle\Tests\Support\Row;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase
{
    public function test_it_counts_and_keeps_a_few_identifiers(): void
    {
        $report = new Report(samples: 2);
        $policy = $this->policy();

        foreach (range(1, 5) as $id) {
            $report->add($policy, Step::Disable, $this->subject($id));
        }

        self::assertSame(5, $report->countFor($policy->subject, Step::Disable));
        self::assertSame(5, $report->countOf(Step::Disable));
        self::assertSame(5, $report->total());
        self::assertSame([1, 2], $report->samplesFor($policy->subject, Step::Disable));
        self::assertFalse($report->isEmpty());
    }

    public function test_an_empty_report_says_so(): void
    {
        self::assertTrue((new Report())->isEmpty());
        self::assertSame(0, (new Report())->total());
        self::assertSame(0, (new Report())->countFor('App\Entity\User', Step::Warn));
    }

    public function test_two_reports_add_up(): void
    {
        $policy = $this->policy();

        $first = new Report();
        $first->add($policy, Step::Warn, $this->subject(1));

        $second = new Report();
        $second->add($policy, Step::Warn, $this->subject(2));
        $second->add($policy, Step::Erase, $this->subject(3));

        $first->merge($second);

        self::assertSame(2, $first->countFor($policy->subject, Step::Warn));
        self::assertSame(1, $first->countFor($policy->subject, Step::Erase));
        self::assertSame([1, 2], $first->samplesFor($policy->subject, Step::Warn));
    }

    public function test_it_gives_a_plain_array_for_the_commands(): void
    {
        $report = new Report(dryRun: true);
        $report->add($this->policy(), Step::Erase, $this->subject(4));

        self::assertSame([
            'dry_run' => true,
            'total' => 1,
            'lines' => [
                ['subject' => 'App\Entity\User', 'step' => 'erase', 'count' => 1, 'ids' => [4]],
            ],
        ], $report->toArray());
    }

    private function policy(): Policy
    {
        return new Policy('App\Entity\User', Duration::parse('1 year'), Ending::Delete);
    }

    private function subject(int $id): Subject
    {
        return new Subject('App\Entity\User', $id, new Row($id), static fn (object $r, string $f): mixed => null);
    }
}
