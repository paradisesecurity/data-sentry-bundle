<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterGeneratorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('paradise_security.data_sentry.service_registry.indexing_generator')) {
            return;
        }

        $registry = $container->getDefinition('paradise_security.data_sentry.service_registry.indexing_generator');

        $generators = [];

        foreach ($container->findTaggedServiceIds('paradise_security.data_sentry.indexing_generator') as $id => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['generator'])) {
                    throw new \InvalidArgumentException('Tagged indexing generators needs to have a `generator` attribute.');
                }

                $name = $attribute['generator'];
                $generators[] = $name;

                $registry->addMethodCall('register', [$name, new Reference($id)]);
            }
        }

        $container->setParameter('paradise_security.data_sentry.indexing_generators', $generators);
    }
}
