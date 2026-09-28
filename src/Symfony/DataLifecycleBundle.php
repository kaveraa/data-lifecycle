<?php

declare(strict_types=1);

namespace Kaveraa\DataLifecycle\Symfony;

use Kaveraa\DataLifecycle\Anonymiser;
use Kaveraa\DataLifecycle\Doctrine\DoctrineDriver;
use Kaveraa\DataLifecycle\Doctrine\PropertyNames;
use Kaveraa\DataLifecycle\Driver;
use Kaveraa\DataLifecycle\Fields;
use Kaveraa\DataLifecycle\Lifecycle;
use Kaveraa\DataLifecycle\PolicyRegistry;
use Kaveraa\DataLifecycle\Runner;
use Kaveraa\DataLifecycle\Symfony\Command\ReportCommand;
use Kaveraa\DataLifecycle\Symfony\Command\RunCommand;
use Kaveraa\DataLifecycle\SystemClock;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Bundle Symfony : la configuration data_lifecycle, les services du paquet, les
 * deux commandes console et le signal d'activité.
 *
 * Symfony bundle: the data_lifecycle configuration, the package services, the
 * two console commands and the activity signal.
 *
 * Activation dans config/bundles.php :
 *
 *     Kaveraa\DataLifecycle\Symfony\DataLifecycleBundle::class => ['all' => true],
 *
 * Configuration (config/packages/data_lifecycle.yaml) :
 *
 *     data_lifecycle:
 *         limit: 1000
 *         subjects:
 *             App\Entity\User:
 *                 keep_for: 3 years
 *                 warn_before: ['30 days', '7 days']
 *                 grace: 30 days
 *                 anonymise: { email: email, name: text }
 */
final class DataLifecycleBundle extends AbstractBundle
{
    protected string $extensionAlias = 'data_lifecycle';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new OptionalAliasPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $fields = new Fields();

        $definition->rootNode()
            ->children()
                ->booleanNode('dry_run')
                    ->info('Mode observation : ne rien écrire, seulement dire ce qui se passerait.')
                    ->defaultFalse()
                ->end()
                ->integerNode('limit')
                    ->info('Nombre de lignes maximum par étape et par règle.')
                    ->min(1)
                    ->defaultValue(1000)
                ->end()
                ->arrayNode('fields')
                    ->info('Noms des colonnes lues et écrites sur vos tables.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('since')->defaultValue($fields->since)->end()
                        ->scalarNode('warn_stage')->defaultValue($fields->warnStage)->end()
                        ->scalarNode('warned_at')->defaultValue($fields->warnedAt)->end()
                        ->scalarNode('disabled_at')->defaultValue($fields->disabledAt)->end()
                        ->scalarNode('anonymised_at')->defaultValue($fields->anonymisedAt)->end()
                    ->end()
                ->end()
                ->arrayNode('anonymiser')
                    ->info('Valeurs de remplacement au moment de l\'anonymisation.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('email_domain')->defaultValue('anonymous.invalid')->end()
                        ->scalarNode('redacted_text')->defaultValue('[removed]')->end()
                        ->scalarNode('anonymous_name')->defaultValue('Anonymous')->end()
                        ->scalarNode('pepper')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('activity')
                    ->info('Signal d\'activité : mise à jour du dernier signe de vie de la personne connectée.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('throttle')
                            ->info('Minutes entre deux écritures. 0 : pas d\'écoute du tout.')
                            ->min(0)
                            ->defaultValue(15)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('subjects')
                    ->info('Les règles, par classe. Le contenu est celui de Policy::fromArray().')
                    ->useAttributeAsKey('class')
                    ->normalizeKeys(false)
                    ->variablePrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('discover')
                    ->info('Classes dont les attributs #[KeepFor] et compagnie sont lus.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    /**
     * DoctrineBundle est-il installé ? Pendant le chargement des extensions, le
     * conteneur reçu ne connaît pas les autres extensions : on regarde la liste
     * des bundles du noyau.
     */
    private static function hasDoctrine(ContainerBuilder $builder): bool
    {
        if (!$builder->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, string> $bundles */
        $bundles = (array) $builder->getParameter('kernel.bundles');

        return isset($bundles['DoctrineBundle']);
    }

    /**
     * @param array{
     *     dry_run: bool,
     *     limit: int,
     *     fields: array<string, string>,
     *     anonymiser: array{email_domain: string, redacted_text: string, anonymous_name: string, pepper: string},
     *     activity: array{throttle: int},
     *     subjects: array<string, mixed>,
     *     discover: list<string>,
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('data_lifecycle.dry_run', $config['dry_run'])
            ->set('data_lifecycle.limit', $config['limit'])
            ->set('data_lifecycle.activity.throttle', $config['activity']['throttle']);

        $services = $container->services();

        $services->set(Fields::class)
            ->factory([Fields::class, 'fromArray'])
            ->args([$config['fields']]);

        $services->set(Anonymiser::class)
            ->args([
                $config['anonymiser']['email_domain'],
                $config['anonymiser']['redacted_text'],
                $config['anonymiser']['anonymous_name'],
                $config['anonymiser']['pepper'],
            ]);

        // Horloge du paquet. L'alias Psr\Clock\ClockInterface n'est posé que si
        // l'application n'en a pas déjà un (voir OptionalAliasPass).
        $services->set('data_lifecycle.clock', SystemClock::class);

        $services->set(PolicyRegistry::class)
            ->factory([PolicyBuilder::class, 'build'])
            ->args([service(Fields::class), $config['discover'], $config['subjects']]);

        if (!self::hasDoctrine($builder)) {
            // Sans DoctrineBundle il n'y a pas de pilote : les règles restent
            // lisibles, le reste attend l'ORM.
            return;
        }

        $services->set(PropertyNames::class);

        $services->set(DoctrineDriver::class)
            ->args([service('doctrine.orm.entity_manager'), service(PropertyNames::class)]);

        $services->alias(Driver::class, DoctrineDriver::class);

        $services->set(Runner::class)
            ->args([
                service(Driver::class),
                service(ClockInterface::class),
                service(Anonymiser::class),
                service(EventDispatcherInterface::class)->nullOnInvalid(),
            ]);

        $services->set(Lifecycle::class)
            ->args([
                service(PolicyRegistry::class),
                service(Runner::class),
                service(Driver::class),
                service(ClockInterface::class),
                service(EventDispatcherInterface::class)->nullOnInvalid(),
            ]);

        $services->set(RunCommand::class)
            ->args([service(Lifecycle::class), $config['dry_run'], $config['limit']])
            ->tag('console.command');

        $services->set(ReportCommand::class)
            ->args([service(Lifecycle::class), service(PolicyRegistry::class), $config['limit']])
            ->tag('console.command');

        if ($config['activity']['throttle'] <= 0) {
            return;
        }

        $services->set(CurrentUser::class)
            ->args([service('security.token_storage')->nullOnInvalid()]);

        $services->set(ActivityListener::class)
            ->args([
                service('doctrine.orm.entity_manager'),
                service(PolicyRegistry::class),
                service(ClockInterface::class),
                service(CurrentUser::class),
                $config['activity']['throttle'],
                service(PropertyNames::class),
            ])
            ->tag('kernel.event_subscriber');
    }
}
