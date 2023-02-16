<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\DataSentryBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\OnClearEventArgs;
use Doctrine\Persistence\PropertyChangedListener;
use ParadiseSecurity\Component\DataSentry\Model\EncryptableInterface;
use ParadiseSecurity\Component\DataSentry\Processor\EntityProcessorInterface;
use ParadiseSecurity\Component\DataSentry\Request\RequestInterface;

use function get_class;
use function is_null;
use function is_subclass_of;
use function spl_object_hash;

final class EntityListener
{
    public const DEFAULT_EVENTS = [
        Events::onClear,
        Events::onFlush,
        Events::postFlush,
        Events::postLoad,
    ];

    public array $decodedRegistry = [];

    /**
     * Before flushing the objects to the database, we modify their plaintext value to the encrypted value. Since we want the data to remain decrypted on the entity after a flush, we have to write the decrypted value back to the entity.
     */
    private array $postFlushDecryptQueue = [];

    public function __construct(
        private EntityProcessorInterface $processor,
        private array $validEntitiesList,
    ) {
    }

    /**
     * Encrypt the properties of an entity before being inserted into the database.
     *
     * @param OnFlushEventArgs $onFlushEventArgs
     */
    public function onFlush(OnFlushEventArgs $arguments): void
    {
        $objectManager = $arguments->getObjectManager();
        $unitOfWork = $objectManager->getUnitOfWork();

        $this->postFlushDecryptQueue = [];

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->proccessOnFlushEvent($entity, $objectManager, $unitOfWork);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->proccessOnFlushEvent($entity, $objectManager, $unitOfWork);
        }
    }

    public function onClear(OnClearEventArgs $args): void
    {
        unset($this->decodedRegistry, $this->postFlushDecryptQueue);

        $this->decodedRegistry = [];
        $this->postFlushDecryptQueue = [];

        $this->processor->cleanup();
    }

    /**
     * Makes the decrypted information available after the entity has been persisted.
     */
    public function postFlush(PostFlushEventArgs $arguments): void
    {
        $unitOfWork = $arguments->getObjectManager()->getUnitOfWork();

        foreach ($this->postFlushDecryptQueue as $pair) {
            $fieldPairs = $pair['fields'];
            $entity = $pair['entity'];

            if (!$unitOfWork->isInIdentityMap($entity)) {
                continue;
            }

            $oid = spl_object_hash($entity);

            foreach ($fieldPairs as $fieldPair) {
                $field = $fieldPair['field'];
                $field->setValue($entity, $fieldPair['value']);
                $unitOfWork->setOriginalEntityProperty($oid, $field->getName(), $fieldPair['value']);
            }

            $this->addToDecodedRegistry($oid);
        }

        $this->postFlushDecryptQueue = [];
    }

    /**
     * Decrypts the properties of an entity.
     */
    public function postLoad(LifecycleEventArgs $arguments): void
    {
        $entity = $arguments->getObject();

        if (!$this->isValidEntity($entity)) {
            return;
        }

        $unitOfWork = $arguments->getObjectManager()->getUnitOfWork();

        $hash = spl_object_hash($entity);

        if (!$this->hasInDecodedRegistry($hash) && $this->proccessPostLoadEvent($entity, $unitOfWork)) {
            $this->addToDecodedRegistry($hash);
        }
    }

    private function proccessPostLoadEvent(
        EncryptableInterface $entity,
        PropertyChangedListener $unitOfWork,
    ): bool {
        $result = $this->processor->process($entity, RequestInterface::DECRYPTION_REQUEST_TYPE);

        $hash = spl_object_hash($entity);

        $metadata = $this->processor->metadata()->find($hash);

        if (is_null($metadata)) {
            return $result;
        }

        foreach ($metadata->getProperties() as $name => $property) {
            $value = $property->getValue($entity);

            $unitOfWork->setOriginalEntityProperty($hash, $name, $value);
        }

        return $result;
    }

    private function proccessOnFlushEvent(
        object $entity,
        EntityManagerInterface $objectManager,
        PropertyChangedListener $unitOfWork,
    ): bool {
        if (!$this->isValidEntity($entity)) {
            return false;
        }

        $metadata = $objectManager->getClassMetadata(get_class($entity));

        $fields = [];

        foreach ($metadata->getReflectionProperties() as $reflectionProperty) {
            $fields[$reflectionProperty->getName()] = [
                'field' => $reflectionProperty,
                'value' => $reflectionProperty->getValue($entity),
            ];
        }

        $hash = spl_object_hash($entity);

        $this->postFlushDecryptQueue[$hash] = [
            'entity' => $entity,
            'fields' => $fields,
        ];

        $result = $this->processor->process($entity, RequestInterface::ENCRYPTION_REQUEST_TYPE);

        $unitOfWork->recomputeSingleEntityChangeSet(
            $objectManager->getClassMetadata(get_class($entity)),
            $entity
        );

        return $result;
    }

    private function isValidEntity(object $entity): bool
    {
        if (!($entity instanceof EncryptableInterface)) {
            return false;
        }

        foreach ($this->validEntitiesList as $className) {
            if (is_subclass_of($entity, $className)) {
                return true;
            }

            if ($entity instanceof $className) {
                return true;
            }
        }

        return false;
    }

    private function addToDecodedRegistry(string $entityHash): void
    {
        $this->decodedRegistry[$entityHash] = true;
    }

    /**
     * Check if the entity is in the decoded registry.
     */
    private function hasInDecodedRegistry(string $entityHash): bool
    {
        return isset($this->decodedRegistry[$entityHash]);
    }
}
