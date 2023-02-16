<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\Test;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ParadiseSecurityDataSentryBundleTest extends KernelTestCase
{
    public function testServicesAreInitializable()
    {
        static::bootKernel();

        /** @var Container $container */
        $container = self::$kernel->getContainer();

        $serviceIds = array_filter($container->getServiceIds(), fn (string $serviceId): bool => str_starts_with($serviceId, 'paradise_security.'));

        foreach ($serviceIds as $id) {
            Assert::assertNotNull($container->get($id, ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
    }
}
