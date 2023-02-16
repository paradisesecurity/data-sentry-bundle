<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle;

use ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection\Compiler\RegisterEncryptorsPass;
use ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection\Compiler\RegisterGeneratorsPass;
use ParadiseSecurity\Bundle\DataSentryBundle\DependencyInjection\Compiler\RegisterTransformersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ParadiseSecurityDataSentryBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterEncryptorsPass());
        $container->addCompilerPass(new RegisterGeneratorsPass());
        $container->addCompilerPass(new RegisterTransformersPass());
    }
}
