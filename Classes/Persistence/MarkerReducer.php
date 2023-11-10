<?php
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

use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * A class to reduce the size of objects in order to be able to persist them in a database
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @api
 */
class MarkerReducer
{

    /**
     * Namespace Keyword
     *
     * @const string
     */
    const NAMESPACE_KEYWORD = 'TX_ACCELERATOR_NAMESPACES';


    /**
     * Namespace Keyword (old version)
     *
     * @const string
     * @deprecated
     */
    const NAMESPACE_KEYWORD_OLD = 'RKW_MAILER_NAMESPACES';


    /**
     * Namespace Keyword for arrays
     *
     * @const string
     */
    const NAMESPACE_ARRAY_KEYWORD = 'TX_ACCELERATOR_NAMESPACES_ARRAY';


    /**
     * Namespace Keyword for arrays (old version)
     *
     * @const string
     * @deprecated
     */
    const NAMESPACE_ARRAY_KEYWORD_OLD = 'RKW_MAILER_NAMESPACES_ARRAY';


    /**
     * implode
     * transform objects into simple references
     *
     * @param array $marker
     * @return array
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function implode(array $marker): array
    {
        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        /** @var \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper $dataMapper */
        $dataMapper = $objectManager->get(DataMapper::class);
        foreach ($marker as $key => $value) {

            // replace current entry with "table => uid" reference
            // keep current variable name, don't use "unset"
            if (is_object($value)) {

                // Normal DomainObject
                if ($value instanceof AbstractEntity) {

                    $namespace = filter_var(
                        $dataMapper->getDataMap(get_class($value))->getClassName(),
                        FILTER_SANITIZE_STRING
                    );

                    if ($value->_isNew()) {
                        self::getLogger()->log(
                            LogLevel::WARNING,
                            sprintf(
                                'Object with namespace %s in marker-array is not persisted and will be '
                                    . 'stored as serialized object in the database. This may cause performance issues!',
                                $namespace
                            )
                        );
                    } else {

                        $marker[$key] = self::NAMESPACE_KEYWORD . ' ' . $namespace . ":" . $value->getUid();
                        self::getLogger()->log(
                            LogLevel::DEBUG,
                            sprintf(
                                'Replacing object with namespace %s and uid %s in marker-array.',
                                $namespace,
                                $value->getUid()
                            )
                        );
                    }

                // ObjectStorage or QueryResult
                } else {

                    if ($value instanceof \Iterator) {

                        // rewind is crucial in live-context!
                        $value->rewind();

                        if (
                            (
                                ($value instanceof QueryResultInterface)
                                && ($firstObject = $value->getFirst())
                            )
                            || (
                                ($value instanceof ObjectStorage)
                                && ($firstObject = $value->current())
                            )
                            && ($firstObject instanceof AbstractEntity)
                        ) {

                            $newValues = array();
                            $namespace = filter_var($dataMapper->getDataMap(get_class($firstObject))->getClassName(), FILTER_SANITIZE_STRING);
                            $replaceObjectStorage = true;
                            foreach ($value as $object) {
                                if ($object instanceof AbstractEntity) {

                                    if ($object->_isNew()) {

                                        $replaceObjectStorage = false;
                                        self::getLogger()->log(
                                            LogLevel::WARNING, sprintf(
                                                'Object with namespace %s in marker-array is not persisted.'
                                                    . ' The object storage it belongs to will be stored as serialized '
                                                    . 'object in the database. This may cause performance issues!',
                                                $namespace
                                            )
                                        );
                                        break;

                                    } else {

                                        $newValues[] = $namespace . ":" . $object->getUid();
                                        self::getLogger()->log(
                                            LogLevel::DEBUG,
                                            sprintf(
                                                'Replacing object with namespace %s and uid %s in marker-array.',
                                                $namespace,
                                                $object->getUid()
                                            )
                                        );
                                    }
                                }
                            }
                            if ($replaceObjectStorage) {
                                $marker[$key] = self::NAMESPACE_ARRAY_KEYWORD . ' ' . implode(',', $newValues);
                            }

                        } else {

                            if (! count($value)) {
                                self::getLogger()->log(
                                    LogLevel::DEBUG,
                                    sprintf(
                                        'Object of class %s in marker-array is empty and will be stored as '
                                            . 'serialized object in the database.',
                                        get_class($value)
                                    )
                                );
                            } else {
                                self::getLogger()->log(
                                    LogLevel::WARNING,
                                    sprintf(
                                        'Object of class %s in marker-array will be stored as serialized '
                                            . 'object in the database. This may cause performance issues!',
                                        get_class($value)
                                    )
                                );
                            }
                        }
                    }
                }
            }
        }

        return $marker;
    }


    /**
     * explode
     * transform simple references to objects
     *
     * @param array $marker
     * @return array
     */
    public static function explode(array $marker): array
    {
        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        foreach ($marker as $key => $value) {

            // check for keyword
            if (
                (is_string($value))
                && (
                    (strpos(trim($value), self::NAMESPACE_KEYWORD) === 0)
                    || (strpos(trim($value), self::NAMESPACE_ARRAY_KEYWORD) === 0)
                    || (strpos(trim($value), self::NAMESPACE_KEYWORD_OLD) === 0)
                    || (strpos(trim($value), self::NAMESPACE_ARRAY_KEYWORD_OLD) === 0)
                )
            ) {

                // check if we have an array here
                $isArray = (bool)(strpos(trim($value), self::NAMESPACE_ARRAY_KEYWORD) === 0)
                    || (strpos(trim($value), self::NAMESPACE_ARRAY_KEYWORD_OLD) === 0);

                self::getLogger()->log(
                    LogLevel::DEBUG,
                    sprintf(
                        'Detection of objectStorage: %s.',
                        intval($isArray)
                    )
                );

                /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
                $objectStorage = $objectManager->get(ObjectStorage::class);

                // clean value from keyword
                $cleanedValue = trim(
                    str_replace(
                        array(
                            self::NAMESPACE_ARRAY_KEYWORD,
                            self::NAMESPACE_KEYWORD,
                            self::NAMESPACE_ARRAY_KEYWORD_OLD,
                            self::NAMESPACE_KEYWORD_OLD,
                        ),
                        '',
                        $value
                    )
                );

                // Go through list of objects. May be comma-separated in case of QueryResultInterface or ObjectStorage
                $listOfObjectDefinitions = GeneralUtility::trimExplode(',', $cleanedValue);
                foreach ($listOfObjectDefinitions as $objectDefinition) {

                    // explode namespace and uid
                    $explodedValue = GeneralUtility::trimExplode(':', $objectDefinition);
                    $namespace = trim($explodedValue[0]);
                    $uid = intval($explodedValue[1]);

                    if (class_exists($namespace)) {

                        // @todo Find a way to get the repository namespace instead of this replace
                        $repositoryName = str_replace('Model', 'Repository', $namespace) . 'Repository';
                        if (class_exists($repositoryName)) {

                            /** @var \TYPO3\CMS\Extbase\Persistence\Repository $repository */
                            $repository = $objectManager->get($repositoryName);

                            // build query - we fetch everything here!
                            $query = $repository->createQuery();
                            $query->getQuerySettings()->setRespectStoragePage(false);
                            $query->getQuerySettings()->setIgnoreEnableFields(true);
                            $query->getQuerySettings()->setIncludeDeleted(true);
                            $query->matching(
                                $query->equals('uid', $uid)
                            )->setLimit(1);


                            if ($result = $query->execute()->getFirst()) {

                                $objectStorage->attach($result);
                                self::getLogger()->log(
                                    LogLevel::DEBUG,
                                    sprintf(
                                        'Replacing object with namespace %s and uid %s in marker-array.',
                                        $namespace,
                                        $result->getUid()
                                    )
                                );
                            }
                        }
                    }
                }

                // add complete objectStorage OR only the first item of it - depending on keyword
                if ($isArray) {
                    $marker[$key] = $objectStorage;
                } else {

                    // if object not found AND no object storage, delete empty key
                    if ($objectStorage->count() > 0) {
                        $objectStorage->rewind();
                        $marker[$key] = $objectStorage->current();
                    } else {
                        unset($marker[$key]);
                    }
                }
            }
        }

        return $marker;
    }


    /**
     * Returns logger instance
     *
     * @return Logger
     */
    protected static function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }
}
