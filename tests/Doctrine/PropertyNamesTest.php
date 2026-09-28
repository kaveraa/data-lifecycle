<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine;

use Kaveraa\DataLifecycle\Doctrine\PropertyNames;
use Kaveraa\DataLifecycle\Doctrine\UnknownProperty;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Contact;
use Kaveraa\DataLifecycle\Tests\Doctrine\Entity\Member;

/**
 * Les noms des règles sont pensés pour des colonnes SQL, le DQL veut des propriétés.
 */
final class PropertyNamesTest extends DoctrineTestCase
{
    public function test_snake_case_becomes_camel_case(): void
    {
        self::assertSame('lastActiveAt', PropertyNames::camel('last_active_at'));
        self::assertSame('lifecycleWarnStage', PropertyNames::camel('lifecycle_warn_stage'));
        self::assertSame('email', PropertyNames::camel('email'));
    }

    public function test_the_property_is_found_in_the_metadata(): void
    {
        $names = new PropertyNames();
        $meta = $this->em->getClassMetadata(Member::class);

        self::assertSame('lastActiveAt', $names->of($meta, 'last_active_at', 'since'));
        self::assertSame('disabledAt', $names->of($meta, 'disabled_at', 'disabled_at'));
        self::assertSame('email', $names->of($meta, 'email', 'anonymise'));
    }

    public function test_a_property_already_in_snake_case_is_kept_as_is(): void
    {
        $names = new PropertyNames();
        $meta = $this->em->getClassMetadata(Contact::class);

        self::assertSame('last_seen_at', $names->of($meta, 'last_seen_at', 'since'));
        self::assertSame(['last_seen_at', 'lastSeenAt'], $names->tried('last_seen_at'));
    }

    public function test_an_optional_field_that_does_not_exist_is_skipped(): void
    {
        $names = new PropertyNames();

        self::assertNull($names->find($this->em->getClassMetadata(Contact::class), 'lifecycle_warn_stage'));
        self::assertNull($names->find($this->em->getClassMetadata(Contact::class), 'lifecycle_warn_stage'));
    }

    public function test_the_message_says_which_property_is_missing_and_on_which_class(): void
    {
        $names = new PropertyNames();

        $this->expectException(UnknownProperty::class);
        $this->expectExceptionMessage(sprintf(
            'The lifecycle field "anonymised_at" of "%s" is named "anonymised_at", but the class has no such property '
            . '(tried "anonymised_at", "anonymisedAt"). Add the property to the entity, or point that field to an existing one.',
            Contact::class,
        ));

        $names->of($this->em->getClassMetadata(Contact::class), 'anonymised_at', 'anonymised_at');
    }
}
