<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterEncryptorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('paradise_security.data_sentry.service_registry.encryptor')) {
            return;
        }

        $registry = $container->getDefinition('paradise_security.data_sentry.service_registry.encryptor');

        $encryptors = [];

        foreach ($container->findTaggedServiceIds('paradise_security.data_sentry.encryptor') as $id => $attributes) {
            foreach ($attributes as $attribute) {
                if (!isset($attribute['name'])) {
                    throw new \InvalidArgumentException('Tagged encryptors needs to have a `name` attribute.');
                }

                $name = $attribute['name'];
                $encryptors[] = $name;

                $registry->addMethodCall('register', [$name, new Reference($id)]);
            }
        }

        $container->setParameter('paradise_security.data_sentry.encryptors', $encryptors);
    }
}
