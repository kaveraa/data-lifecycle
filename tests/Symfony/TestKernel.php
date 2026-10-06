<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Tests\Symfony;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Kaveraa\DataLifecycle\Symfony\DataLifecycleBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal Symfony application: FrameworkBundle + DoctrineBundle + DataLifecycleBundle.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /** @var list<string> services made public for the tests */
    public const EXPOSED = [
        'Kaveraa\DataLifecycle\Lifecycle',
        'Kaveraa\DataLifecycle\PolicyRegistry',
        'Kaveraa\DataLifecycle\Runner',
        'Kaveraa\DataLifecycle\Driver',
        'Kaveraa\DataLifecycle\Fields',
        'Kaveraa\DataLifecycle\Anonymiser',
        'Kaveraa\DataLifecycle\Symfony\Command\RunCommand',
        'Kaveraa\DataLifecycle\Symfony\Command\ReportCommand',
        'Kaveraa\DataLifecycle\Symfony\ActivityListener',
        'Psr\Clock\ClockInterface',
        'Psr\EventDispatcher\EventDispatcherInterface',
        'doctrine.orm.entity_manager',
    ];

    /**
     * @param array<string, mixed> $lifecycle the data_lifecycle configuration
     */
    public function __construct(private readonly array $lifecycle = [])
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new DataLifecycleBundle();
    }

    /**
     * Everything Symfony writes stays in a temporary folder: the package
     * repository must never receive a generated file.
     */
    public function getProjectDir(): string
    {
        $dir = sys_get_temp_dir() . '/data-lifecycle-tests/' . md5(serialize($this->lifecycle));

        if (!is_dir($dir . '/config')) {
            mkdir($dir . '/config', 0o777, true);
        }

        return $dir;
    }

    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        // The package services are private: the tests need to read them.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (TestKernel::EXPOSED as $id) {
                    if ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);

                        continue;
                    }

                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true]);

        $container->extension('doctrine', [
            'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true, 'logging' => false, 'profiling' => false],
            'orm' => [
                'mappings' => [
                    'Test' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__) . '/Doctrine/Entity',
                        'prefix' => 'Kaveraa\DataLifecycle\Tests\Doctrine\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->extension('data_lifecycle', $this->lifecycle);
    }
}
