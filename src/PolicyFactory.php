<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle;

use Kaveraa\DataLifecycle\Attribute\DisableFirst;
use Kaveraa\DataLifecycle\Attribute\KeepFor;
use Kaveraa\DataLifecycle\Attribute\ThenAnonymise;
use Kaveraa\DataLifecycle\Attribute\ThenDelete;
use Kaveraa\DataLifecycle\Attribute\WarnBefore;
use Kaveraa\DataLifecycle\Exception\InvalidPolicy;
use ReflectionClass;

/**
 * Reads the attributes of a class and turns them into a retention policy.
 */
final class PolicyFactory
{
    public function __construct(private readonly Fields $defaults = new Fields())
    {
    }

    /**
     * Returns null if the class does not have #[KeepFor].
     */
    public function fromAttributes(string $subject): ?Policy
    {
        $reflection = new ReflectionClass($subject);

        $keepFor = $reflection->getAttributes(KeepFor::class)[0] ?? null;

        if ($keepFor === null) {
            return null;
        }

        $keepFor = $keepFor->newInstance();

        $anonymise = ($reflection->getAttributes(ThenAnonymise::class)[0] ?? null)?->newInstance();
        $delete = ($reflection->getAttributes(ThenDelete::class)[0] ?? null)?->newInstance();

        if ($anonymise !== null && $delete !== null) {
            throw InvalidPolicy::twoEndings($subject);
        }

        if ($anonymise === null && $delete === null) {
            throw InvalidPolicy::noEnd($subject);
        }

        $disable = ($reflection->getAttributes(DisableFirst::class)[0] ?? null)?->newInstance();

        $fields = $this->defaults;

        if ($keepFor->since !== null) {
            $fields = $fields->withSince($keepFor->since);
        }

        if ($disable?->field !== null) {
            $fields = $fields->withDisabledAt($disable->field);
        }

        $warnBefore = array_map(
            static fn (\ReflectionAttribute $one): string => $one->newInstance()->before,
            $reflection->getAttributes(WarnBefore::class),
        );

        return new Policy(
            subject: $subject,
            keepFor: Duration::parse($keepFor->duration),
            ending: $anonymise !== null ? Ending::Anonymise : Ending::Delete,
            warnBefore: array_values($warnBefore),
            grace: $disable !== null ? Duration::parse($disable->grace) : null,
            anonymise: $anonymise?->fields ?? [],
            forceDelete: $delete?->force ?? false,
            fields: $fields,
        );
    }
}
