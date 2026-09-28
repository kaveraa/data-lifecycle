<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Symfony;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Symfony\Command\ReportCommand;
use Kaveraa\DataLifecycle\Symfony\Command\RunCommand;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Member;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Session;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandsTest extends BundleTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function config(bool $dryRun = false): array
    {
        return [
            'dry_run' => $dryRun,
            'subjects' => [
                Member::class => [
                    'keep_for' => '2 years',
                    'warn_before' => ['30 days'],
                    'grace' => '15 days',
                    'anonymise' => ['email' => 'email', 'name' => 'text'],
                ],
            ],
            'discover' => [Session::class],
        ];
    }

    public function test_lifecycle_run_warns_disables_and_deletes(): void
    {
        $container = $this->boot(self::config());
        [$memberId, $sessionId] = $this->seed($container);

        $tester = $this->tester($container, RunCommand::class);
        $tester->execute([], ['decorated' => false]);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Member', $output);
        self::assertStringContainsString('rappel', $output);
        self::assertStringContainsString('désactivation', $output);
        self::assertStringContainsString('Session', $output);
        self::assertStringContainsString('effacement', $output);

        $entities = $this->entities($container);
        $entities->clear();

        $member = $entities->find(Member::class, $memberId);

        self::assertSame(1, $member->lifecycleWarnStage);
        self::assertNotNull($member->disabledAt);
        self::assertNull($member->anonymisedAt);
        self::assertNull($entities->find(Session::class, $sessionId));
    }

    public function test_lifecycle_run_with_dry_run_writes_nothing(): void
    {
        $container = $this->boot(self::config());
        [$memberId, $sessionId] = $this->seed($container);

        $tester = $this->tester($container, RunCommand::class);
        $tester->execute(['--dry-run' => true], ['decorated' => false]);

        self::assertStringContainsString('observation', $tester->getDisplay());

        $this->assertNothingChanged($container, $memberId, $sessionId);
    }

    public function test_dry_run_in_the_configuration_forces_observe_mode(): void
    {
        $container = $this->boot(self::config(dryRun: true));
        [$memberId, $sessionId] = $this->seed($container);

        $tester = $this->tester($container, RunCommand::class);
        $tester->execute([], ['decorated' => false]);

        self::assertStringContainsString('data_lifecycle.dry_run', $tester->getDisplay());

        $this->assertNothingChanged($container, $memberId, $sessionId);
    }

    public function test_lifecycle_run_can_be_limited_to_one_entity_and_one_step(): void
    {
        $container = $this->boot(self::config());
        [$memberId, $sessionId] = $this->seed($container);

        $tester = $this->tester($container, RunCommand::class);
        $tester->execute(['--subject' => ['Member'], '--step' => ['warn']], ['decorated' => false]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('Member', $output);
        self::assertStringNotContainsString('Session', $output);

        $entities = $this->entities($container);
        $entities->clear();

        $member = $entities->find(Member::class, $memberId);

        self::assertSame(1, $member->lifecycleWarnStage);
        self::assertNull($member->disabledAt);
        self::assertNotNull($entities->find(Session::class, $sessionId));
    }

    public function test_lifecycle_report_recalls_the_policies_without_writing(): void
    {
        $container = $this->boot(self::config());
        [$memberId, $sessionId] = $this->seed($container);

        $tester = $this->tester($container, ReportCommand::class);
        $tester->execute([], ['decorated' => false]);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Conservation', $output);
        self::assertStringContainsString('2 years', $output);
        self::assertStringContainsString('15 days', $output);
        self::assertStringContainsString('anonymisation (email, name)', $output);
        self::assertStringContainsString('30 days', $output);
        self::assertStringContainsString('suppression', $output);
        self::assertStringContainsString('ligne(s) seraient traitées', $output);

        $this->assertNothingChanged($container, $memberId, $sessionId);
    }

    public function test_an_unknown_entity_is_reported(): void
    {
        $container = $this->boot(self::config());

        $tester = $this->tester($container, RunCommand::class);

        $this->expectExceptionMessage('No lifecycle policy is registered for "Invoice"');

        $tester->execute(['--subject' => ['Invoice']], ['decorated' => false]);
    }

    /**
     * @return array{int, int}
     */
    private function seed(ContainerInterface $container): array
    {
        $entities = $this->entities($container);

        $member = new Member('zoe@example.test', 'Zoe', new DateTimeImmutable('2000-01-01 09:00:00'));
        $session = new Session(new DateTimeImmutable('2000-01-01 09:00:00'));

        $entities->persist($member);
        $entities->persist($session);
        $entities->flush();

        return [(int) $member->id, (int) $session->id];
    }

    private function assertNothingChanged(ContainerInterface $container, int $memberId, int $sessionId): void
    {
        $entities = $this->entities($container);
        $entities->clear();

        $member = $entities->find(Member::class, $memberId);

        self::assertNotNull($member);
        self::assertSame('zoe@example.test', $member->email);
        self::assertSame('Zoe', $member->name);
        self::assertNull($member->lifecycleWarnStage);
        self::assertNull($member->lifecycleWarnedAt);
        self::assertNull($member->disabledAt);
        self::assertNull($member->anonymisedAt);
        self::assertNotNull($entities->find(Session::class, $sessionId));
    }

    /**
     * @param class-string<Command> $command
     */
    public function test_a_limit_that_makes_no_sense_stops_the_command(): void
    {
        $container = $this->boot(self::config());

        $run = $this->tester($container, RunCommand::class);
        self::assertSame(Command::FAILURE, $run->execute(['--limit' => '0']));
        self::assertStringContainsString('Limite invalide', $run->getDisplay());

        $report = $this->tester($container, ReportCommand::class);
        self::assertSame(Command::FAILURE, $report->execute(['--limit' => 'beaucoup']));
        self::assertStringContainsString('Limite invalide', $report->getDisplay());
    }

    private function tester(ContainerInterface $container, string $command): CommandTester
    {
        $service = $container->get($command);

        self::assertInstanceOf(Command::class, $service);

        return new CommandTester($service);
    }
}
