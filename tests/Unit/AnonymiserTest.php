<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Unit;

use DateTimeImmutable;
use Kaveraa\DataLifecycle\Anonymiser;
use Kaveraa\DataLifecycle\Duration;
use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\Strategy;
use Kaveraa\DataLifecycle\Subject;
use Kaveraa\DataLifecycle\Tests\Support\Row;
use PHPUnit\Framework\TestCase;

final class AnonymiserTest extends TestCase
{
    public function test_it_replaces_every_field_of_the_policy(): void
    {
        $values = (new Anonymiser())->values($this->policy([
            'email' => Strategy::Email,
            'name' => Strategy::Text,
            'note' => Strategy::Nullify,
            'city' => Strategy::EmptyText,
            'points' => Strategy::Zero,
            'birth_date' => Strategy::YearOnly,
        ]), $this->subject());

        self::assertSame('anonymous-7@anonymous.invalid', $values['email']);
        self::assertSame('Anonymous', $values['name']);
        self::assertNull($values['note']);
        self::assertSame('', $values['city']);
        self::assertSame(0, $values['points']);
        self::assertInstanceOf(DateTimeImmutable::class, $values['birth_date']);
        self::assertSame('1990-01-01 00:00:00', $values['birth_date']->format('Y-m-d H:i:s'));
    }

    public function test_auto_reads_the_name_of_the_field(): void
    {
        $values = (new Anonymiser())->values($this->policy(['email' => Strategy::Auto, 'name' => Strategy::Auto]), $this->subject());

        self::assertSame('anonymous-7@anonymous.invalid', $values['email']);
        self::assertSame('[removed]', $values['name']);
    }

    public function test_a_hash_hides_the_value_but_keeps_equal_values_equal(): void
    {
        $anonymiser = new Anonymiser(pepper: 'secret');
        $policy = $this->policy(['email' => Strategy::Hash]);

        $first = $anonymiser->values($policy, $this->subject(7, 'lea@example.org'))['email'];
        $again = $anonymiser->values($policy, $this->subject(9, 'lea@example.org'))['email'];
        $other = $anonymiser->values($policy, $this->subject(8, 'sam@example.org'))['email'];

        self::assertSame($first, $again);
        self::assertNotSame($first, $other);
        self::assertStringNotContainsString('lea', (string) $first);
        self::assertSame(32, strlen((string) $first));
    }

    public function test_a_hash_of_nothing_stays_nothing(): void
    {
        $values = (new Anonymiser())->values($this->policy(['note' => Strategy::Hash]), $this->subject());

        self::assertNull($values['note']);
    }

    public function test_the_application_chooses_its_own_words(): void
    {
        $anonymiser = new Anonymiser(emailDomain: 'exemple.invalid', redactedText: 'efface', anonymousName: 'Anonyme');

        $values = $anonymiser->values($this->policy([
            'email' => Strategy::Email,
            'name' => Strategy::Text,
            'city' => Strategy::Redact,
        ]), $this->subject());

        self::assertSame('anonymous-7@exemple.invalid', $values['email']);
        self::assertSame('Anonyme', $values['name']);
        self::assertSame('efface', $values['city']);
    }

    public function test_an_identifier_that_is_not_a_number_still_gives_one_address(): void
    {
        $values = (new Anonymiser())->values($this->policy(['email' => Strategy::Email]), $this->subject('9f1c-4b2e'));

        self::assertSame('anonymous-9f1c4b2e@anonymous.invalid', $values['email']);
    }

    /**
     * @param array<string, Strategy> $fields
     */
    private function policy(array $fields): Policy
    {
        return new Policy(
            subject: 'App\Entity\User',
            keepFor: Duration::parse('1 year'),
            ending: Ending::Anonymise,
            anonymise: $fields,
        );
    }

    private function subject(int|string $id = 7, string $email = 'lea@example.org'): Subject
    {
        $row = new Row($id, [
            'email' => $email,
            'name' => 'Lea',
            'note' => null,
            'city' => 'Lyon',
            'points' => 120,
            'birth_date' => new DateTimeImmutable('1990-06-15 12:30:00'),
        ]);

        return new Subject('App\Entity\User', $id, $row, static fn (object $r, string $f): mixed => $r->get($f));
    }
}
