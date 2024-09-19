<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Persistence;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Madj2k\Accelerator\Persistence\Representations\ReducedCollection;
use Madj2k\Accelerator\Persistence\Representations\ReducedObject;
use Madj2k\Accelerator\Persistence\Representations\ReducedReference;
use Madj2k\Accelerator\Persistence\Representations\ReducedValue;
use ReflectionClass;
use ReflectionProperty;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * A class to reduce the size of objects in order to be able to persist them in a database.
 *
 * This class is used to transform objects and object collections into simpler references that can be stored
 * more efficiently in a database. It handles both persisted and non-persisted objects, reducing only the
 * persisted objects.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Christian Dilger <c.dilger@addorange.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @api
 */
final class MarkerReducerAdvanced implements MarkerReducerInterface
{
    /**
     * Reduces the size of objects in an array by replacing persisted objects with references.
     * Also processes non-persisted objects by checking their properties for persisted objects.
     *
     * @param array $marker The array containing objects to be reduced.
     * @return array<string, mixed> An associative array with strings as keys.
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function implode(array $marker): array
    {
        /** @var ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        /** @var DataMapper $dataMapper */
        $dataMapper = $objectManager->get(DataMapper::class);

        /** @var array<string, mixed> $reducedObjects */
        $reducedObjects = [];

        foreach ($marker as $key => $value) {
            $reducedObjects[$key] = self::processValue($key, $value, $dataMapper);
        }

        return $reducedObjects;
    }


    /**
     * Processes a single value, reducing it if it's a persisted object or an iterable containing persisted objects.
     * If the object is not persisted, it recursively checks its properties for persisted objects to reduce.
     *
     * @param string $key The key of the value.
     * @param mixed $value The value to be processed.
     * @param DataMapper $dataMapper The data mapper used to retrieve class metadata.
     * @return mixed The processed value or the original value if not reduced.
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    protected static function processValue(string $key, $value, DataMapper $dataMapper)
    {
        if (is_object($value)) {

            if ($value instanceof AbstractEntity) {
                return self::processDomainObject($value, $dataMapper);
            }

            if ($value instanceof \Iterator) {
                return self::processIterable($value, $dataMapper);
            }
        }

        return $value;
    }


    /**
     * Processes a single domain object, reducing it if it's persisted.
     * If the object is not persisted, it recursively checks its properties for persisted objects to reduce.
     * The original object is not modified; instead, reductions are stored in a separate array.
     *
     * @param AbstractEntity $object The domain object to be processed.
     * @param DataMapper $dataMapper The data mapper used to retrieve class metadata.
     * @return ReducedValue The processed value as a ReducedValue.
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    protected static function processDomainObject(AbstractEntity $object, DataMapper $dataMapper): ReducedValue
    {
        if (!$object->_isNew()) {
            $namespace = filter_var(
                $dataMapper->getDataMap(get_class($object))->getClassName(),
                FILTER_SANITIZE_STRING
            );

            self::getLogger()->log(
                LogLevel::DEBUG,
                sprintf(
                    'Replacing object with namespace %s and uid %s.',
                    $namespace,
                    $object->getUid()
                )
            );

            return new ReducedReference($namespace, $object->getUid());
        }

        return self::reduceObjectProperties($object, $dataMapper);
    }


    /**
     * Recursively processes the properties of a non-persisted object, reducing any persisted objects it references.
     * The original object is not modified; instead, reductions are stored in a ReducedObject instance.
     *
     * @param AbstractEntity $object The non-persisted object to process.
     * @param DataMapper $dataMapper The data mapper used to retrieve class metadata.
     * @return \Madj2k\Accelerator\Persistence\Representations\ReducedObject The object with its properties processed.
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    protected static function reduceObjectProperties(AbstractEntity $object, DataMapper $dataMapper): ReducedObject
    {
        /** @var \ReflectionClass $reflection */
        $reflection = new ReflectionClass($object);

        /** @var array<string, mixed> $reducedData */
        $reducedData = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PRIVATE) as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);

            if (is_object($value)) {
                if ($value instanceof ObjectStorage) {
                    $reducedData[$property->getName()] = self::processObjectStorage($value, $dataMapper);
                } else {
                    $reducedData[$property->getName()] = self::processValue($property->getName(), $value, $dataMapper);
                }
            } else {
                $reducedData[$property->getName()] = $value;
            }
        }

        return new ReducedObject($reflection->getName(), $reducedData);
    }


    /**
     * Processes an ObjectStorage, ensuring that it only contains persisted objects.
     * If the ObjectStorage contains non-persisted objects, it returns the original ObjectStorage.
     *
     * @param ObjectStorage $value The ObjectStorage to be processed.
     * @param DataMapper $dataMapper The data mapper used to retrieve class metadata.
     * @return ReducedCollection|ObjectStorage The ReducedCollection if all objects are persisted, otherwise the original ObjectStorage.
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    protected static function processObjectStorage(ObjectStorage $value, DataMapper $dataMapper)
    {
        $value->rewind(); // Ensure the ObjectStorage is rewound before iterating
        $references = [];

        foreach ($value as $object) {
            if ($object instanceof AbstractEntity) {
                if ($object->_isNew()) {
                    self::getLogger()->log(
                        LogLevel::WARNING,
                        'ObjectStorage contains a non-persisted object and will not be reduced.'
                    );

                    return $value;
                }

                $processedValue = self::processDomainObject($object, $dataMapper);
                if ($processedValue instanceof ReducedReference) {
                    $references[] = $processedValue;
                }
            }
        }

        return new ReducedCollection($references);
    }


    /**
     * Processes an iterable collection of domain objects, reducing the collection if all objects are persisted.
     *
     * @param \Iterator $value The iterable collection to be processed.
     * @param DataMapper $dataMapper The data mapper used to retrieve class metadata.
     * @return ReducedCollection A reference to the collection if all objects are persisted.
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    protected static function processIterable(\Iterator $value, DataMapper $dataMapper): ReducedCollection
    {
        $value->rewind();
        $references = [];

        foreach ($value as $object) {
            if ($object instanceof AbstractEntity) {
                $references[] = self::processDomainObject($object, $dataMapper);
            }
        }

        return new ReducedCollection($references);
    }


    /**
     * Rebuilds reduced objects from their references or ReducedObject instances in the marker array.
     *
     * @param array $marker The array containing reduced references or ReducedObject instances.
     * @return array<string, mixed> An associative array with rebuilt objects or the original values if no reduction occurred.
     * @throws \ReflectionException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public static function explode(array $marker): array
    {
        $rebuiltObjects = [];

        foreach ($marker as $key => $reducedValue) {
            if ($reducedValue instanceof ReducedObject) {
                $rebuiltObjects[$key] = self::rebuildObjectFromProperties($reducedValue);
            } elseif ($reducedValue instanceof ReducedCollection) {
                $rebuiltObjects[$key] = self::rebuildCollection($reducedValue->getReferences());
            } elseif ($reducedValue instanceof ReducedReference) {
                $rebuiltObjects[$key] = self::rebuildObject((string)$reducedValue);
            } else {
                $rebuiltObjects[$key] = $reducedValue;
            }
        }

        return $rebuiltObjects;
    }


    /**
     * Rebuilds an object from a ReducedObject instance.
     *
     * @param ReducedObject $reducedObject The ReducedObject instance to rebuild the original object.
     * @return object|null The rebuilt object or null if the class does not exist.
     * @throws \ReflectionException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected static function rebuildObjectFromProperties(ReducedObject $reducedObject): ?object
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $className = $reducedObject->getKey();

        if (!class_exists($className)) {
            return null;
        }

        $object = $objectManager->get($className);

        foreach ($reducedObject->getProperties() as $propertyName => $value) {
            $reflectionProperty = new ReflectionProperty($object, $propertyName);
            $reflectionProperty->setAccessible(true);

            if ($value instanceof ReducedReference) {
                $reflectionProperty->setValue($object, self::rebuildObject((string)$value));
            } elseif ($value instanceof ReducedCollection) {
                $reflectionProperty->setValue($object, self::rebuildCollection($value->getReferences()));
            } elseif (is_array($value)) {
                $reflectionProperty->setValue($object, self::rebuildValue($value));
            } else {
                $reflectionProperty->setValue($object, $value);
            }
        }

        return $object;
    }


    /**
     * Rebuilds a value from a reduced reference or recursively from a ReducedObject instance.
     *
     * @param array $properties The reduced properties of the object.
     * @return mixed The rebuilt object, collection, or original value.
     * @throws \ReflectionException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected static function rebuildValue(array $properties)
    {
        $rebuiltProperties = [];

        foreach ($properties as $key => $value) {
            if (is_array($value)) {
                $rebuiltProperties[$key] = self::rebuildValue($value);
            } elseif ($value instanceof ReducedCollection) {
                $rebuiltProperties[$key] = self::rebuildCollection($value->getReferences());
            } elseif ($value instanceof ReducedReference) {
                $rebuiltProperties[$key] = self::rebuildObject((string)$value);
            } else {
                $rebuiltProperties[$key] = $value;
            }
        }

        return $rebuiltProperties;
    }


    /**
     * Rebuilds an object or a collection of objects from a reduced reference.
     *
     * @param string $value The reduced reference string.
     * @return \TYPO3\CMS\Extbase\DomainObject\AbstractEntity|null The rebuilt object or collection, or null if not found.
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected static function rebuildObject(string $value): ?AbstractEntity
    {

        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $explodedValue = GeneralUtility::trimExplode(':', $value);
        $namespace = trim($explodedValue[0]);
        $uid = (int)$explodedValue[1];

        if (class_exists($namespace)) {
            $repositoryName = str_replace('Model', 'Repository', $namespace) . 'Repository';

            if (class_exists($repositoryName)) {
                $repository = $objectManager->get($repositoryName);
                $query = $repository->createQuery();
                $query->getQuerySettings()->setRespectStoragePage(false);
                $query->getQuerySettings()->setIgnoreEnableFields(true);
                $query->getQuerySettings()->setIncludeDeleted(true);
                $query->matching(
                    $query->equals('uid', $uid)
                )->setLimit(1);

                return $query->execute()->getFirst();
            }
        }

        return null;
    }


    /**
     * Rebuilds a collection of objects from a reduced reference.
     *
     * @param array $references The reduced reference strings for the collection.
     * @return ObjectStorage|null The rebuilt collection, or null if not found.
     * @throws \ReflectionException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected static function rebuildCollection(array $references): ?ObjectStorage
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $objectStorage = $objectManager->get(ObjectStorage::class);

        foreach ($references as $reference) {
            if ($reference instanceof ReducedReference) {
                $object = self::rebuildObject((string)$reference);
            }

            if ($reference instanceof ReducedObject) {
                $object = self::rebuildObjectFromProperties($reference);
            }

            if ($object instanceof AbstractEntity) {
                $objectStorage->attach($object);
            }
        }

        return $objectStorage;
    }


    /**
     * Returns logger instance
     *
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    protected static function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }
}
