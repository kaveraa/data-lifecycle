<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Attribute;

use Attribute;
use Kaveraa\DataLifecycle\Strategy;

/**
 * At the end, the row stays but the listed fields are replaced.
 *
 *     #[ThenAnonymise('email', 'name')]
 *     #[ThenAnonymise(['email' => Strategy::Hash, 'birth_date' => Strategy::YearOnly])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ThenAnonymise
{
    /** @var array<string, Strategy> */
    public readonly array $fields;

    /**
     * @param string|array<int|string, Strategy|string> ...$fields
     */
    public function __construct(string|array ...$fields)
    {
        $map = [];

        foreach ($fields as $entry) {
            if (is_string($entry)) {
                $map[$entry] = Strategy::Auto;

                continue;
            }

            foreach ($entry as $field => $strategy) {
                if (is_int($field)) {
                    $map[(string) $strategy] = Strategy::Auto;

                    continue;
                }

                $map[$field] = $strategy instanceof Strategy ? $strategy : Strategy::from((string) $strategy);
            }
        }

        $this->fields = $map;
    }
}
