<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Tests\Integration\Persistence;

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

use Madj2k\Accelerator\Persistence\MarkerReducer;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * MarkerReducerTest
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class MarkerReducerTest extends FunctionalTestCase
{

    /**
     * @const
     */
    const FIXTURE_PATH = __DIR__ . '/MarkerReducerTest/Fixtures';


    /**
     * @var string[]
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/accelerator',
        'typo3conf/ext/core_extended'
    ];


    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [ ];


    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager|null
     */
    private ?ObjectManager $objectManager = null;


    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager|null
     */
    private ?PersistenceManager $persistenceManager = null;


    /**
     * @var \TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository|null
     */
    private ?FrontendUserRepository $frontendUserRepository = null;


    /**
     * Setup
     * @throws \Exception
     */
    protected function setUp(): void
    {

        parent::setUp();

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Global.csv');
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
            ]
        );

        $this->persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $this->frontendUserRepository = GeneralUtility::makeInstance(FrontendUserRepository::class);
    }


    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function implodeUsingPersistedObjectsReturnsCompletelyReducedArray()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given three items are  persisted objects in the database
         * Given the fourth item is an objectStorage with two objects
         * Given these two objects are persisted in the database
         * When the method is called
         * Then the marker array returns four items
         * Then the first three items are reduced to object-placeholders consisting of namespace and the uid
         * Then the fourth item is reduced to an array-placeholder with a comma-separated list of object-placeholders consisting of namespace and the uid
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = $this->frontendUserRepository->findByIdentifier(3);

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityTwo);
        $objectStorage->attach($entityThree);

        $marker = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test3' => $entityThree,
            'test4' => $objectStorage,
        ];

        $expected = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => 'TX_ACCELERATOR_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2,TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
        ];

        $result = MarkerReducer::implode($marker);

        self::assertCount(4, $expected);
        self::assertEquals($expected['test1'], $result['test1']);
        self::assertEquals($expected['test1'], $result['test1']);
        self::assertEquals($expected['test3'], $result['test3']);
        self::assertEquals($expected['test4'], $result['test4']);

    }


    /**
     * @test
     * @throws \Exception
     */
    public function implodeUsingMixedObjectsInObjectStorageLeavesObjectStorageUntouched()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given the first and third item are persisted objects in the database
         * Given the second of the objects is not persisted in the database
         * Given the fourth item is an objectStorage with two objects
         * Given the first of the objects is persisted in the database
         * Given the second of the objects is not persisted in the database
         * When the method is called
         * Then the marker array returns four items
         * Then the first and the third item are reduced to object-placeholders consisting of namespace and the uid
         * Then the second item contains the complete non-persisted object
         * Then the fourth item contains the object-storage with the complete objects
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = GeneralUtility::makeInstance(FrontendUser::class);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = $this->frontendUserRepository->findByIdentifier(3);

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityOne);
        $objectStorage->attach($entityTwo);

        $marker = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test3' => $entityThree,
            'test4' => $objectStorage,
        ];

        $expected = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => $entityTwo,
            'test3' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => $objectStorage,
        ];

        $result = MarkerReducer::implode($marker);

        self::assertCount(4, $expected);
        self::assertEquals($expected['test1'], $result['test1']);
        self::assertEquals($expected['test1'], $result['test1']);
        self::assertEquals($expected['test3'], $result['test3']);
        self::assertEquals($expected['test4'], $result['test4']);
    }


    /**
     * @test
     * @throws \Exception
     */
    public function implodeLeavesNonObjectsUntouched()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains three items
         * Given the first item is a persisted object in the database
         * Given the second item is an array
         * Given the third item is a simple string
         * When the method is called
         * Then the marker array returns three items
         * Then the first and the third item are reduced to object-placeholders consisting of namespace and the uid
         * Then the second item contains the complete non-persisted object
         * Then the fourth item contains the object-storage with the complete objects
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        $marker = [
            'test1' => $entityOne,
            'test2' => [0, 12],
            'test3' => 'example string',
        ];

        $expected = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => [0, 12],
            'test3' => 'example string',
        ];

        $result = MarkerReducer::implode($marker);

        self::assertCount(3, $expected);
        self::assertEquals($expected['test1'], $result['test1']);
        self::assertEquals($expected['test1'], $result['test1']);
        self::assertEquals($expected['test3'], $result['test3']);
    }


    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function explodeConsistingOfPersistedObjectsReturnsCompleteObjectArray()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given three of the items are references to existing objects in the database
         * Given the fourth item is a reference to an objectStorage with two objects
         * Given these two objects are references to existing objects in the database
         * When the method is called
         * Then the marker array returns four items
         * Then the first three items are contain objects of the given type
         * Then the first three items are contain the objects identified by the given uid
         * Then the fourth item contains an array with two objects of the given type
         * Then the two objects of the fourth item are the objects identified by the given uid
         */

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = $this->frontendUserRepository->findByIdentifier(3);

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityTwo);
        $objectStorage->attach($entityThree);

        $marker = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => 'TX_ACCELERATOR_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2,TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
        ];

        $expected = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test3' => $entityThree,
            'test4' => $objectStorage,
        ];

        $result = MarkerReducer::explode($marker);

        self::assertCount(4, $expected);
        self::assertInstanceOf(FrontendUser::class, $result['test1']);
        self::assertInstanceOf(FrontendUser::class, $result['test2']);
        self::assertInstanceOf(FrontendUser::class, $result['test3']);
        self::assertEquals($expected['test1']->getUid(), $result['test1']->getUid());
        self::assertEquals($expected['test2']->getUid(), $result['test2']->getUid());
        self::assertEquals($expected['test3']->getUid(), $result['test3']->getUid());

        /** @var ObjectStorage $resultStorage */
        $resultStorage = $result['test4'];
        self::assertInstanceOf(ObjectStorage::class, $resultStorage);
        self::assertCount(2, $resultStorage);

        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityTwo->getUid(), $resultStorage->current()->getUid());
        $resultStorage->next();
        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityThree->getUid(), $resultStorage->current()->getUid());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function explodeConsistingOfPersistedButDeletedObjectsReturnsCompleteObjectArray()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given three of the items are references to existing but deleted objects in the database
         * Given the fourth item is a reference to an objectStorage with two objects
         * Given these two objects are references to existing but deleted objects in the database
         * When the method is called
         * Then the marker array returns four items
         * Then the first three items are contain objects of the given type
         * Then the first three items are contain the objects identified by the given uid
         * Then the fourth item contains an array with two objects of the given type
         * Then the two objects of the fourth item are the objects identified by the given uid
         */

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);
        $this->frontendUserRepository->remove($entityOne);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);
        $this->frontendUserRepository->remove($entityTwo);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = $this->frontendUserRepository->findByIdentifier(3);
        $this->frontendUserRepository->remove($entityThree);
        $this->persistenceManager->persistAll();

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityTwo);
        $objectStorage->attach($entityThree);

        $marker = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => 'TX_ACCELERATOR_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2,TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
        ];

        $expected = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test3' => $entityThree,
            'test4' => $objectStorage,
        ];

        $result = MarkerReducer::explode($marker);

        self::assertCount(4, $expected);
        self::assertInstanceOf(FrontendUser::class, $result['test1']);
        self::assertInstanceOf(FrontendUser::class, $result['test2']);
        self::assertInstanceOf(FrontendUser::class, $result['test3']);
        self::assertEquals($expected['test1']->getUid(), $result['test1']->getUid());
        self::assertEquals($expected['test2']->getUid(), $result['test2']->getUid());
        self::assertEquals($expected['test3']->getUid(), $result['test3']->getUid());

        /** @var ObjectStorage $resultStorage */
        $resultStorage = $result['test4'];
        self::assertInstanceOf(ObjectStorage::class, $resultStorage);
        self::assertCount(2, $resultStorage);

        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityTwo->getUid(), $resultStorage->current()->getUid());
        $resultStorage->next();
        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityThree->getUid(), $resultStorage->current()->getUid());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function explodeConsistingOfPersistedButHiddenObjectsReturnsCompleteObjectArray()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given three of the items are references to existing but disabled objects in the database
         * Given the fourth item is a reference to an objectStorage with two objects
         * Given these two objects are references to existing but disabled objects in the database
         * When the method is called
         * Then the marker array returns four items
         * Then the first three items are contain objects of the given type
         * Then the first three items are contain the objects identified by the given uid
         * Then the fourth item contaons an array with two objects of the given type and uid
         * Then the two objects of the fourth item are the objects identified by the given uid
         */

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = $this->frontendUserRepository->findByIdentifier(3);

        // set objects as disabled!
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_users');
        $queryBuilder
            ->update('fe_users')
            ->set('disable', 1)
            ->execute();

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityTwo);
        $objectStorage->attach($entityThree);

        $marker = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => 'TX_ACCELERATOR_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2,TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
        ];

        $expected = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test3' => $entityThree,
            'test4' => $objectStorage,
        ];

        $result = MarkerReducer::explode($marker);

        self::assertCount(4, $expected);
        self::assertInstanceOf(FrontendUser::class, $result['test1']);
        self::assertInstanceOf(FrontendUser::class, $result['test2']);
        self::assertInstanceOf(FrontendUser::class, $result['test3']);
        self::assertEquals($expected['test1']->getUid(), $result['test1']->getUid());
        self::assertEquals($expected['test2']->getUid(), $result['test2']->getUid());
        self::assertEquals($expected['test3']->getUid(), $result['test3']->getUid());

        /** @var ObjectStorage $resultStorage */
        $resultStorage = $result['test4'];
        self::assertInstanceOf(ObjectStorage::class, $resultStorage);
        self::assertCount(2, $resultStorage);

        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityTwo->getUid(), $resultStorage->current()->getUid());
        $resultStorage->next();
        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityThree->getUid(), $resultStorage->current()->getUid());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function explodeUsingNonExistingObjectsReturnsReducedObjectArray()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given the first two of the items are references to existing objects in the database
         * Given the third item is a reference to non-existing object in the database
         * Given the fourth item is a reference to an objectStorage with two objects
         * Given the first object is a reference to existing object in the database
         * Given the second object is a reference to non-existing object in the database
         * When the method is called
         * Then the marker array returns three items
         * Then the first two items contain objects of the given type
         * Then the first two items contain the objects identified by the given uid
         * Then the third item is an object storage with one item
         * Then the first item of the object storage contains an object of the given type
         * Then the first item of the object storage is the object identified by the given uid
         * Then the non-existing object in the object storage is missing
         */

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = $this->frontendUserRepository->findByIdentifier(3);

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityThree);

        $marker = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:9999',
            'test4' => 'TX_ACCELERATOR_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3,Madj2k\CoreExtended\Domain\Model\MediaSources:9999',
        ];

        $expected = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test4' => $objectStorage,
        ];

        $result = MarkerReducer::explode($marker);

        self::assertCount(3, $expected);
        self::assertInstanceOf(FrontendUser::class, $result['test1']);
        self::assertInstanceOf(FrontendUser::class, $result['test2']);
        self::assertEquals($expected['test1']->getUid(), $result['test1']->getUid());
        self::assertEquals($expected['test2']->getUid(), $result['test2']->getUid());

        /** @var ObjectStorage $resultStorage */
        $resultStorage = $result['test4'];
        self::assertInstanceOf(ObjectStorage::class, $resultStorage);
        self::assertCount(1, $resultStorage);

        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityThree->getUid(), $resultStorage->current()->getUid());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function explodeUsingOnlyNonExistingObjectsInObjectStorageReturnsEmptyObjectStorage()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given the first two of the items are references to existing objects in the database
         * Given the third item is a reference to non-existing object in the database
         * Given the fourth item is a reference to an objectStorage with two objects
         * Given the two objects are a reference to non-existing objects in the database
         * When the method is called
         * Then the marker array returns three items
         * Then the first two items contain objects of the given type
         * Then the first two items contain the objects identified by the given uid
         * Then the non-existing object is missing
         * Then the third item is an object storage with no elements
         */

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);

        $expected = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test4' => $objectStorage,
        ];

        $marker = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:9999',
            'test4' => 'TX_ACCELERATOR_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:8888,Madj2k\CoreExtended\Domain\Model\MediaSources:9999',
        ];

        $result = MarkerReducer::explode($marker);

        self::assertCount(3, $expected);
        self::assertInstanceOf(FrontendUser::class, $result['test1']);
        self::assertInstanceOf(FrontendUser::class, $result['test2']);
        self::assertEquals($expected['test1']->getUid(), $result['test1']->getUid());
        self::assertEquals($expected['test2']->getUid(), $result['test2']->getUid());

        /** @var ObjectStorage $resultStorage */
        $resultStorage = $result['test4'];
        self::assertInstanceOf(ObjectStorage::class, $resultStorage);
        self::assertCount(0, $resultStorage);
    }


    /**
     * @test
     * @throws \Exception
     */
    public function explodeLeavesItemsWithoutKeywordsUntouched()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given the first two of the items are references to existing objects in the database
         * Given the third item is a reference to an existing object in the database, but with wrong keyword-prefix
         * Given the fourth item is a reference to an objectStorage with two existing object in the database, but with wrong keyword-prefix
         * When the method is called
         * Then the marker array returns four items
         * Then the first two items contain objects of the given type
         * Then the first two items contain the objects identified by the given uid
         * Then the third item is returned as untouched string
         * Then the fourth item is  returned as untouched string
         */

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);

        $expected = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test3' => 'RKW_TESTER_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => 'RKW_TESTER_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:8888,Madj2k\CoreExtended\Domain\Model\MediaSources:9999',

        ];

        $marker = [
            'test1' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TX_ACCELERATOR_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'RKW_TESTER_NAMESPACES TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => 'RKW_TESTER_NAMESPACES_ARRAY TYPO3\CMS\Extbase\Domain\Model\FrontendUser:8888,Madj2k\CoreExtended\Domain\Model\MediaSources:9999',
        ];


        $result = MarkerReducer::explode($marker);

        self::assertCount(4, $expected);
        self::assertInstanceOf(FrontendUser::class, $result['test1']);
        self::assertInstanceOf(FrontendUser::class, $result['test2']);
        self::assertEquals($expected['test1']->getUid(), $result['test1']->getUid());
        self::assertEquals($expected['test2']->getUid(), $result['test2']->getUid());
        self::assertEquals($expected['test3'], $result['test3']);
        self::assertEquals($expected['test4'], $result['test4']);

    }


    //=============================================


    /**
     * TearDown
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
