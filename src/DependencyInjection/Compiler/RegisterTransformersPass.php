<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterTransformersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('paradise_security.data_sentry.service_registry.blind_index_transformation')) {
            return;
        }

        $registry = $container->getDefinition('paradise_security.data_sentry.service_registry.blind_index_transformation');

        $transformers = [];

        foreach ($container->findTaggedServiceIds('paradise_security.data_sentry.blind_index_transformation') as $id => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['transformer'])) {
                    throw new \InvalidArgumentException('Tagged blind indexing transformers needs to have a `transformer` attribute.');
                }

                $name = $attribute['transformer'];
                $transformers[] = $name;

                $registry->addMethodCall('register', [$name, new Reference($id)]);
            }
        }

        $container->setParameter('paradise_security.data_sentry.blind_index_transformers', $transformers);
    }
}
