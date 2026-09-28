<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Laravel;

use Kaveraa\DataLifecycle\Ending;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Tests\Laravel\Fixtures\Ticket;

/**
 * Une classe déclarée à la fois par attribut et par configuration : la
 * configuration l'emporte.
 */
final class ConfigWinsOverAttributesTest extends TestCase
{
    /**
     * @return array<class-string, array<string, mixed>>
     */
    protected function subjects(): array
    {
        return [
            Ticket::class => [
                'keep_for' => '5 years',
                'anonymise' => ['label' => 'redact'],
            ],
        ];
    }

    public function test_the_configuration_replaces_the_attribute(): void
    {
        $policy = $this->app->make(PolicyRegistry::class)->get(Ticket::class);

        self::assertSame('5 years', (string) $policy->keepFor);
        self::assertSame(Ending::Anonymise, $policy->ending);
        self::assertFalse($policy->forceDelete);
    }
}
