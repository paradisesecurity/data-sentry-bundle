<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\Test\EventListener;

use PHPUnit\Framework\Assert;
use ParadiseSecurity\Component\DataSentry\Test\FakeModelTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class EntityListenerTest extends KernelTestCase
{
    use FakeModelTrait;

    public function testPostLoadEventCanDecryptEntity()
    {
        static::bootKernel();

        $unitOfWork = $this->mockDoctrineUnitOfWork();
        $objectManager = $this->mockDoctrineObjectManager();
        $lifeCycleEvent = $this->mockDoctrinePersistenceEvent('LifecycleEventArgs');

        $encryptedConfig = $this->getEncryptedFakeModelConfig();

        $fake = $this->createFakeModel($encryptedConfig);

        $unitOfWork->expects($this->exactly(5))->method('setOriginalEntityProperty');
        $objectManager->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWork);
        $lifeCycleEvent->expects($this->once())->method('getObjectManager')->willReturn($objectManager);
        $lifeCycleEvent->expects($this->once())->method('getObject')->willReturn($fake);

        $listener = static::getContainer()->get('paradise_security.data_sentry.event_listener.entity.test');

        $listener->postLoad($lifeCycleEvent);

        $decryptedConfig = $this->getDecryptedFakeModelConfig();

        $this->assertSame($decryptedConfig['name'], $fake->getName());
        $this->assertSame($decryptedConfig['account_number'], $fake->getAccountNumber());
        $this->assertSame($decryptedConfig['account_number_bi'], $fake->getAccountNumberBi());
        $this->assertSame($decryptedConfig['secret_number'], $fake->getSecretNumber());
        $this->assertSame($decryptedConfig['secret_number_encrypted'], $fake->getSecretNumberEncrypted());
    }

    public function testOnFlushEventCanEncryptEntity()
    {
        static::bootKernel();

        $classMetadata = $this->mockDoctrineClassMetadata();
        $unitOfWork = $this->mockDoctrineUnitOfWork();
        $objectManager = $this->mockDoctrineObjectManager();
        $onFlushEventArgs = $this->mockDoctrineOrmEvent('OnFlushEventArgs');

        $decryptedConfig = $this->getDecryptedFakeModelConfig();
        unset($decryptedConfig['account_number_bi']);

        $fake = $this->createFakeModel($decryptedConfig);

        $reflectionClass = new \ReflectionClass($fake);

        $classMetadata->expects($this->once())->method('getReflectionProperties')->willReturn($reflectionClass->getProperties());
        $unitOfWork->expects($this->once())->method('getScheduledEntityInsertions')->willReturn([$fake]);
        $unitOfWork->expects($this->once())->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->expects($this->once())->method('recomputeSingleEntityChangeSet');
        $objectManager->expects($this->exactly(2))->method('getUnitOfWork')->willReturn($unitOfWork);
        $objectManager->expects($this->exactly(2))->method('getClassMetadata')->willReturn($classMetadata);
        $onFlushEventArgs->expects($this->once())->method('getObjectManager')->willReturn($objectManager);

        $listener = static::getContainer()->get('paradise_security.data_sentry.event_listener.entity.test');

        $listener->onFlush($onFlushEventArgs);

        $encryptedConfig = $this->getEncryptedFakeModelConfig();

        $this->assertStringStartsWith('brng:', $fake->getName());
        $this->assertStringStartsWith('brng:', $fake->getAccountNumber());
        $this->assertSame($encryptedConfig['account_number_bi'], $fake->getAccountNumberBi());
        $this->assertSame($encryptedConfig['secret_number'], $fake->getSecretNumber());
        $this->assertStringStartsWith('brng:', $fake->getSecretNumberEncrypted());

        $postFlushEventArgs = $this->mockDoctrineOrmEvent('PostFlushEventArgs');

        $unitOfWork->expects($this->exactly(1))->method('isInIdentityMap')->willReturn(true);
        $unitOfWork->expects($this->exactly(5))->method('setOriginalEntityProperty');
        $postFlushEventArgs->expects($this->once())->method('getObjectManager')->willReturn($objectManager);

        $listener->postFlush($postFlushEventArgs);

        $this->assertSame($decryptedConfig['name'], $fake->getName());
        $this->assertSame($decryptedConfig['account_number'], $fake->getAccountNumber());
        $this->assertSame(null, $fake->getAccountNumberBi());
        $this->assertSame($decryptedConfig['secret_number'], $fake->getSecretNumber());
        $this->assertSame($decryptedConfig['secret_number_encrypted'], $fake->getSecretNumberEncrypted());
    }

    public function testPostFlushEventCannotDecryptEntity()
    {
        static::bootKernel();

        $unitOfWork = $this->mockDoctrineUnitOfWork();
        $objectManager = $this->mockDoctrineObjectManager();
        $postFlushEventArgs = $this->mockDoctrineOrmEvent('PostFlushEventArgs');

        $encryptedConfig = $this->getEncryptedFakeModelConfig();

        $fake = $this->createFakeModel($encryptedConfig);

        $unitOfWork->expects($this->never())->method('isInIdentityMap');
        $unitOfWork->expects($this->never())->method('setOriginalEntityProperty');
        $objectManager->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWork);
        $postFlushEventArgs->expects($this->once())->method('getObjectManager')->willReturn($objectManager);

        $listener = static::getContainer()->get('paradise_security.data_sentry.event_listener.entity.test');

        $listener->postFlush($postFlushEventArgs);

        $this->assertSame($encryptedConfig['name'], $fake->getName());
        $this->assertSame($encryptedConfig['account_number'], $fake->getAccountNumber());
        $this->assertSame($encryptedConfig['account_number_bi'], $fake->getAccountNumberBi());
        $this->assertSame($encryptedConfig['secret_number'], $fake->getSecretNumber());
        $this->assertSame($encryptedConfig['secret_number_encrypted'], $fake->getSecretNumberEncrypted());
    }

    private function mockDoctrineObjectManager()
    {
        return $this->createMock('\\Doctrine\\ORM\\EntityManager');
    }

    private function mockDoctrineUnitOfWork()
    {
        return $this->createMock('\\Doctrine\\ORM\\UnitOfWork');
    }

    private function mockDoctrinePersistenceEvent($eventType)
    {
        return $this->createMock('\\Doctrine\\Persistence\\Event\\' . $eventType);
    }

    private function mockDoctrineOrmEvent($eventType)
    {
        return $this->createMock('\\Doctrine\\ORM\\Event\\' . $eventType);
    }

    private function mockDoctrineClassMetadata()
    {
        return $this->createMock('\\Doctrine\\ORM\\Mapping\\ClassMetadata');
    }
}
