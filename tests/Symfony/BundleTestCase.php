<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Symfony;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Boots the test application, creates the schema, and puts everything back after.
 */
abstract class BundleTestCase extends TestCase
{
    private ?TestKernel $kernel = null;

    /** @var callable|null exception handler active before the kernel boots */
    private mixed $exceptionHandler = null;

    protected function setUp(): void
    {
        $this->exceptionHandler = self::currentExceptionHandler();
    }

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            (new Filesystem())->remove($this->kernel->getProjectDir());
            $this->kernel->shutdown();
            $this->kernel = null;
        }

        // Symfony sometimes installs an exception handler without removing it.
        while (self::currentExceptionHandler() !== $this->exceptionHandler) {
            restore_exception_handler();
        }
    }

    /**
     * @param array<string, mixed> $config the data_lifecycle configuration
     */
    protected function boot(array $config = []): ContainerInterface
    {
        $this->kernel = new TestKernel($config);

        (new Filesystem())->remove($this->kernel->getCacheDir());

        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        $entities = $container->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entities);

        (new SchemaTool($entities))->createSchema($entities->getMetadataFactory()->getAllMetadata());

        return $container;
    }

    protected function entities(ContainerInterface $container): EntityManagerInterface
    {
        $entities = $container->get('doctrine.orm.entity_manager');

        self::assertInstanceOf(EntityManagerInterface::class, $entities);

        return $entities;
    }

    private static function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
