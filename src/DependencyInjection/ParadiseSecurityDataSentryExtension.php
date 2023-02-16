<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection;

use ParadiseSecurity\Bundle\DataSentryBundle\EventListener\EntityListener;
use ParadiseSecurity\Component\DataSentry\Cache\Cache;
use ParadiseSecurity\Component\DataSentry\Cache\CacheInterface;
use ParadiseSecurity\Component\DataSentry\Cache\Symfony\SymfonyCachePoolAdapter;
use ParadiseSecurity\Component\DataSentry\Encryptor\CipherSweet\CipherSweetAdapter;
use ParadiseSecurity\Component\DataSentry\Encryptor\Encryptor;
use ParadiseSecurity\Component\DataSentry\Encryptor\EncryptorAdapterInterface;
use ParagonIE\CipherSweet\CipherSweet;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class ParadiseSecurityDataSentryExtension extends Extension
{
    protected NameConverterInterface $normalizer;

    public function __construct()
    {
        $this->normalizer = new CamelCaseToSnakeCaseNameConverter();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('services.xml');

        $this->configureEncryptors($config['encryptors'], $container);

        $this->configureListeners($config['listeners'], $container);
    }

    private function configureEncryptors(array $encryptors, ContainerBuilder $container): void
    {
        foreach ($encryptors as $name => $config) {
            $this->createEncryptorServiceDefinition($name, $config, $container);
        }
    }

    private function createEncryptorServiceDefinition(
        string $name,
        array $config,
        ContainerBuilder $container
    ): ?string {
        $encryptorAdapterServiceId = $this->setupEncryptorAdapter($name, $config, $container);
        $cacheAdapterServiceId = $this->setupCacheAdapter($name, $config, $container);

        if (is_null($encryptorAdapterServiceId) || is_null($cacheAdapterServiceId)) {
            return null;
        }

        $cacheDefinition = $this->createServiceDefinition(Cache::class, $container)
            ->addArgument(new Reference($cacheAdapterServiceId))
        ;
        $cacheServiceId = sprintf('paradise_security.data_sentry.cache.%s', $name);
        $container->setDefinition($cacheServiceId, $cacheDefinition);

        $serviceId = sprintf('paradise_security.data_sentry.encryptor.%s', $name);

        $definition = $this->createServiceDefinition(Encryptor::class, $container)
            ->addArgument(new Reference($encryptorAdapterServiceId))
            ->addArgument(new Reference($cacheServiceId))
            ->addTag('paradise_security.data_sentry.encryptor', [
                'name' => $name,
            ])
        ;
        $container->setDefinition($serviceId, $definition);

        return $serviceId;
    }

    private function setupEncryptorAdapter(
        string $name,
        array $config,
        ContainerBuilder $container
    ): ?string {
        $adapter = $config['adapter'];
        $adapterConfig = $config['adapter_config'];

        $method = $this->convertSnakeCaseToCamelCase(sprintf('setup_%s_encryptor_adapter', $adapter));
        if (is_callable([$this, $method]) && isset($adapterConfig[$adapter])) {
            return $this->$method($name, $adapterConfig[$adapter], $container);
        }

        return null;
    }

    private function setupCustomEncryptorAdapter(
        string $name,
        array $config,
        ContainerBuilder $container
    ): ?string {
        if (!isset(($config['service']))) {
            return null;
        }

        $serviceId = $config['service'];

        if ($container->hasDefinition($serviceId)) {
            $className = $container->getDefinition($serviceId)->getClass();

            if (is_a($className, EncryptorAdapterInterface::class, true)) {
                return $serviceId;
            }
        }

        if (!class_exists($serviceId)) {
            return null;
        }

        if (is_a($serviceId, EncryptorAdapterInterface::class, true)) {
            $customServiceId = sprintf('paradise_security.data_sentry.encryptor_adapter.custom_%s', $name);

            $container->setDefinition($customServiceId, new Definition($serviceId));

            return $customServiceId;
        }

        return null;
    }

    private function setupCiphersweetEncryptorAdapter(
        string $name,
        array $config,
        ContainerBuilder $container
    ): ?string {
        if (!isset(($config['cryptography']))) {
            return null;
        }

        $serviceId = sprintf('paradise_security.data_sentry.encryptor_adapter.ciphersweet_%s', $name);

        $cryptography = $config['cryptography'];

        $backendServiceId = sprintf('paragon_ie.ciphersweet.backend.%s_crypto', $cryptography['crypto']);

        $keyProviderServiceId = sprintf('paragon_ie.ciphersweet.key_provider.%s', $cryptography['key_provider']);
        $keyProviderClass = $container->getDefinition($keyProviderServiceId)->getClass();
        $keyProviderDefinition = $this->createServiceDefinition($keyProviderClass, $container)
            ->addArgument($cryptography['key'])
        ;
        $keyProviderCustomServiceId = sprintf('%s.key_provider', $serviceId);
        $container->setDefinition($keyProviderCustomServiceId, $keyProviderDefinition);

        $cipherSweetServiceId = sprintf('%s.engine', $serviceId);
        $cipherSweetDefinition = $this->createServiceDefinition(CipherSweet::class, $container)
            ->addArgument(new Reference($keyProviderCustomServiceId))
            ->addArgument(new Reference($backendServiceId))
        ;
        $container->setDefinition($cipherSweetServiceId, $cipherSweetDefinition);

        $cipherSweetAdapterDefinition = $this->createServiceDefinition(CipherSweetAdapter::class, $container)
            ->addArgument(new Reference($cipherSweetServiceId))
            ->addArgument(new Reference('paradise_security.data_sentry.encryptor.ciphersweet.resolver.blind_index_transformation'))
        ;
        $container->setDefinition($serviceId, $cipherSweetAdapterDefinition);

        return $serviceId;
    }

    private function setupCacheAdapter(
        string $name,
        array $config,
        ContainerBuilder $container
    ): ?string {
        $adapter = $config['cache_adapter'];
        $adapterConfig = $config['cache_adapter_config'];

        $method = $this->convertSnakeCaseToCamelCase(sprintf('setup_%s_cache_adapter', $adapter));
        if (is_callable([$this, $method]) && isset($adapterConfig[$adapter])) {
            return $this->$method($name, $adapterConfig[$adapter], $container);
        }

        return null;
    }

    private function setupCustomCacheAdapter(
        string $name,
        array $config,
        ContainerBuilder $container
    ): ?string {
        if (!isset(($config['service']))) {
            return null;
        }

        $serviceId = $config['service'];

        if ($container->hasDefinition($serviceId)) {
            $className = $container->getDefinition($serviceId)->getClass();

            if (is_a($className, CacheInterface::class, true)) {
                return $serviceId;
            }
        }

        if (!class_exists($serviceId)) {
            return null;
        }

        if (is_a($serviceId, CacheInterface::class, true)) {
            $customServiceId = sprintf('paradise_security.data_sentry.cache_adapter.custom_%s', $name);

            $container->setDefinition($customServiceId, new Definition($serviceId));

            return $customServiceId;
        }

        return null;
    }

    private function setupSymfonyCachePoolCacheAdapter(
        string $name,
        array $config,
        ContainerBuilder $container
    ): ?string {
        if (!isset(($config['cache_pool']))) {
            return null;
        }

        $serviceId = sprintf('paradise_security.data_sentry.cache_adapter.symfony_cache_pool_%s', $name);

        $symfonyCachePoolAdapterDefinition = $this->createServiceDefinition(SymfonyCachePoolAdapter::class, $container)
            ->addArgument(new Reference($config['cache_pool']))
        ;
        $container->setDefinition($serviceId, $symfonyCachePoolAdapterDefinition);

        return $serviceId;
    }

    private function configureListeners(array $listeners, ContainerBuilder $container): void
    {
        foreach ($listeners as $name => $config) {
            $this->createListenerServiceDefinition($name, $config, $container);
        }
    }

    private function createListenerServiceDefinition(
        string $name,
        array $config,
        ContainerBuilder $container
    ): void {
        $events = $this->getActiveListenerEvents($config['events']);

        $serviceId = sprintf('paradise_security.data_sentry.event_listener.entity.%s', $name);

        $definition = $this->createServiceDefinition(
            EntityListener::class,
            $container,
        )
            ->addArgument(new Reference('paradise_security.data_sentry.processor.entity'))
            ->addArgument($this->getEntitiesList($config['entity_class_names'], $container))
        ;

        foreach ($events as $event) {
            $definition
                ->addTag('doctrine.event_listener', [
                    'event' => $event,
                    'connection' => $config['entity_manager']
                ])
            ;
        }

        $container->setDefinition($serviceId, $definition);
    }

    private function getActiveListenerEvents(array $events): array
    {
        $list = [];

        foreach ($events as $event => $enabled) {
            if (in_array($event, EntityListener::DEFAULT_EVENTS) === false) {
                continue;
            }

            if ($enabled === false) {
                continue;
            }

            $list[] = $event;
        }

        return $list;
    }

    private function getEntitiesList(
        array $entities,
        ContainerBuilder $container
    ): array {
        $list = [];

        foreach ($entities as $entity) {
            $entity_class_name = $this->getEntityClassName($entity, $container);

            if ($entity_class_name === null) {
                continue;
            }

            $list[] = $entity_class_name;
        }

        return $list;
    }

    private function getEntityClassName(
        string $entity,
        ContainerBuilder $container
    ): ?string {
        if (class_exists($entity)) {
            return $entity;
        }

        if ($container->hasDefinition($entity)) {
            return $container->getDefinition($entity)->getClass();
        }

        return null;
    }

    private function createServiceDefinition(string $className): Definition
    {
        return new Definition($className);
    }

    private function getOrCreateServiceDefinition(
        string $className,
        ContainerBuilder $container
    ): Definition {
        if (!$container->hasDefinition($className)) {
            return $this->createServiceDefinition($className);
        }

        return $container->getDefinition($className);
    }

    private function convertSnakeCaseToCamelCase(string $string): string
    {
        return $this->normalizer->denormalize($string);
    }

    private function convertCamelCaseToSnakeCase(string $string): string
    {
        return $this->normalizer->normalize($string);
    }
}
