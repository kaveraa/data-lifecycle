<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Kaveraa\DataLifecycle\Anonymiser;
use Kaveraa\DataLifecycle\Doctrine\DoctrineDriver;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\FrozenClock;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\Policy;
use Kaveraa\DataLifecycle\PolicyFactory;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Runner;
use PHPUnit\Framework\TestCase;

/**
 * Un EntityManager SQLite en mémoire, les entités de test, une horloge arrêtée.
 */
abstract class DoctrineTestCase extends TestCase
{
    protected EntityManagerInterface $em;

    protected DoctrineDriver $driver;

    protected FrozenClock $clock;

    protected RecordedEvents $events;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Entity'], true);

        if (method_exists($config, 'enableNativeLazyObjects') && \PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);

        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->clock = FrozenClock::at('2023-01-01 12:00:00');
        $this->driver = new DoctrineDriver($this->em);
        $this->events = new RecordedEvents();
    }

    protected function tearDown(): void
    {
        $this->em->close();
    }

    /**
     * Le point d'entrée du paquet, branché sur les règles lues dans les attributs
     * des classes données.
     */
    protected function lifecycleOf(string ...$classes): Lifecycle
    {
        $registry = new PolicyRegistry(array_map(fn (string $class): Policy => $this->policyOf($class), $classes));

        $runner = new Runner($this->driver, $this->clock, new Anonymiser(), $this->events);

        return new Lifecycle($registry, $runner, $this->driver, $this->clock, $this->events);
    }

    protected function policyOf(string $class, ?Fields $defaults = null): Policy
    {
        $policy = (new PolicyFactory($defaults ?? new Fields()))->fromAttributes($class);

        self::assertNotNull($policy, sprintf('%s should carry #[KeepFor].', $class));

        return $policy;
    }

    /**
     * Relit la ligne depuis la base, sans rien garder en mémoire.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    protected function reload(string $class, int|string $id): ?object
    {
        $this->em->clear();

        return $this->em->find($class, $id);
    }

    protected function save(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }

    protected static function at(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment);
    }
}
