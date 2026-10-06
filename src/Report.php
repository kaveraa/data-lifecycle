<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

/**
 * What was done, or what would be done in observe mode.
 */
final class Report
{
    /** @var array<string, array{subject: string, step: Step, count: int, ids: list<int|string>}> */
    private array $lines = [];

    public function __construct(public readonly bool $dryRun = false, private readonly int $samples = 5)
    {
    }

    public function add(Policy $policy, Step $step, Subject $subject): void
    {
        $key = $policy->subject . '|' . $step->value;

        if (!isset($this->lines[$key])) {
            $this->lines[$key] = ['subject' => $policy->subject, 'step' => $step, 'count' => 0, 'ids' => []];
        }

        ++$this->lines[$key]['count'];

        if (count($this->lines[$key]['ids']) < $this->samples) {
            $this->lines[$key]['ids'][] = $subject->id;
        }
    }

    public function countFor(string $subject, Step $step): int
    {
        return $this->lines[$subject . '|' . $step->value]['count'] ?? 0;
    }

    public function countOf(Step $step): int
    {
        $total = 0;

        foreach ($this->lines as $line) {
            if ($line['step'] === $step) {
                $total += $line['count'];
            }
        }

        return $total;
    }

    public function total(): int
    {
        return array_sum(array_column($this->lines, 'count'));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * @return list<int|string>
     */
    public function samplesFor(string $subject, Step $step): array
    {
        return $this->lines[$subject . '|' . $step->value]['ids'] ?? [];
    }

    /**
     * @return list<array{subject: string, step: Step, count: int, ids: list<int|string>}>
     */
    public function lines(): array
    {
        $lines = array_values($this->lines);

        usort($lines, static fn (array $a, array $b): int => [$a['subject'], $a['step']->value] <=> [$b['subject'], $b['step']->value]);

        return $lines;
    }

    public function merge(self $other): void
    {
        foreach ($other->lines as $key => $line) {
            if (!isset($this->lines[$key])) {
                $this->lines[$key] = $line;

                continue;
            }

            $this->lines[$key]['count'] += $line['count'];
            $this->lines[$key]['ids'] = array_slice(array_merge($this->lines[$key]['ids'], $line['ids']), 0, $this->samples);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'total' => $this->total(),
            'lines' => array_map(
                static fn (array $line): array => [
                    'subject' => $line['subject'],
                    'step' => $line['step']->value,
                    'count' => $line['count'],
                    'ids' => $line['ids'],
                ],
                $this->lines(),
            ),
        ];
    }
}
