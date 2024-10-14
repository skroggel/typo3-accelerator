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
use Madj2k\Accelerator\Persistence\Representations\ReducedCollection;
use Madj2k\Accelerator\Persistence\Representations\ReducedObject;
use Madj2k\Accelerator\Persistence\Representations\ReducedReference;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Domain\Repository\FrontendUserRepository;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
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
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['accelerator']['markerReducerVersion'] = 'advanced';
    }


    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function explodingAnImplodedArrayReturnsAnArrayEqualToTheImplodedOne()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains four items
         * Given the items one and two are persisted objects in the database
         * Given the third items is not persisted object in the database
         * Given the fourth item is an objectStorage with two objects
         * Given these two objects are persisted in the database
         * When the method is called
         * Then the marker array returns four items
         * Then the result should equal the given marker array
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = $this->frontendUserRepository->findByIdentifier(2);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = GeneralUtility::makeInstance(FrontendUser::class);
        $entityThree->setEmail('entity3@example.com');

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityOne);
        $objectStorage->attach($entityTwo);

        $marker = [
            'test1' => $entityOne,
            'test2' => $entityTwo,
            'test3' => $entityThree,
            'test4' => $objectStorage,
            'test5' => [0, 12],
            'test6' => 'example string',
            'test7' => ['key' => 'value']
        ];

        $expected = $marker;

        $implodedMarker = MarkerReducer::implode($marker);
        $result = MarkerReducer::explode($implodedMarker);

        self::assertEquals($expected, $result);
    }


    /**
     * @test
     * @throws \Exception
     */
    public function explodingAnImplodedArrayWithASingleNotPersistedObjectReturnsAnArrayEqualToTheImplodedOne()
    {

        /**
         * Scenario:
         *
         * Given a marker array contains one item
         * Given the item is not persisted object in the database
         * When the method is called
         * Then the marker array returns one item
         * Then the result should equal the given marker array
         */

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = GeneralUtility::makeInstance(FrontendUser::class);
        $entityOne->setEmail('entity1@example.com');

        $marker = [
            'test1' => $entityOne,
        ];

        $expected = $marker;

        $implodedMarker = MarkerReducer::implode($marker);
        $result = MarkerReducer::explode($implodedMarker);

        self::assertEquals($expected, $result);
    }


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
         * Given three items are persisted objects in the database
         * Given the fourth item is an objectStorage with two objects
         * Given these two objects are persisted in the database
         * When the method is called
         * Then the marker array returns four items
         * Then the first three items are reduced to ReducedReference objects represented by namespace and uid, if casted to string
         * Then the fourth item is reduced to a Reduced Collection containing 2 ReducedReference objects represented by namespace and uid, if casted to string
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
            'test1' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
            'test3' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => [
                'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:2',
                'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3'
            ]
        ];

        $result = MarkerReducer::implode($marker);

        self::assertCount(4, $expected);
        self::assertEquals($expected['test1'], (string)$result['test1']);
        self::assertEquals($expected['test2'], (string)$result['test2']);
        self::assertEquals($expected['test3'], (string)$result['test3']);
        self::assertInstanceOf(ReducedCollection::class, $result['test4']);
        self::assertCount(2, $result['test4']->getReferences());
        self::assertEquals($expected['test4'][0], (string)$result['test4']->getReferences()[0]);
        self::assertEquals($expected['test4'][1], (string)$result['test4']->getReferences()[1]);
    }


    /**
     * @test
     * @throws \Exception
     */
    public function implodeUsingMixedObjectsInObjectStorageLeavesNonPersistedObjectsUntouched()
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
         * Then the fourth item contains the object-storage with the referenced objects
         * Then the first of these objects is referenced as a ReducedReference casted to string
         * Then the second of these objects is a complete object
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = GeneralUtility::makeInstance(FrontendUser::class);
        $entityTwo->setEmail('entity2@example.com');

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
            'test1' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => [
                'nonPersistedObject' => [
                    'key' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser',
                    'email' => $entityTwo->getEmail(),
                ]
            ],
            'test3' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:3',
            'test4' => [
                'persistedObject' => [
                    'reference' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
                ],
                'nonPersistedObject' => [
                    'key' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser',
                    'email' => $entityTwo->getEmail(),
                ]
            ]
        ];

        $result = MarkerReducer::implode($marker);

        self::assertCount(4, $expected);
        self::assertEquals($expected['test1'], (string)$result['test1']);
        self::assertEquals($expected['test1'], $result['test1']);
        self::assertEquals($expected['test2']['nonPersistedObject']['key'], $result['test2']->getKey());
        self::assertEquals($expected['test2']['nonPersistedObject']['email'], $result['test2']->getProperties()['email']);
        self::assertEquals($expected['test3'], (string)$result['test3']);
        self::assertCount(2, $result['test4']->getReferences());
        self::assertEquals($expected['test4']['persistedObject']['reference'], (string)$result['test4']->getReferences()[0]);
        self::assertEquals($expected['test4']['nonPersistedObject']['key'], $result['test4']->getReferences()[1]->getKey());
        self::assertEquals($expected['test4']['nonPersistedObject']['email'], $result['test4']->getReferences()[1]->getProperties()['email']);
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
         * Then the first item is reduced to object-placeholders consisting of namespace and the uid if cast to string
         * Then the second item contains the untouched array
         * Then the fourth item contains the untouched string
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
            'test1' => 'TYPO3\CMS\Extbase\Domain\Model\FrontendUser:1',
            'test2' => [0, 12],
            'test3' => 'example string',
        ];

        $result = MarkerReducer::implode($marker);

        self::assertCount(3, $expected);
        self::assertEquals($expected['test1'], (string)$result['test1']);
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
         * Then the first three items are objects of the given type
         * Then the first three items are the objects identified by the given uid
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
            'test1' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 1),
            'test2' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
            'test3' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
            'test4' => new ReducedCollection([
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
            ])
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
    public function explodeConsistingOfMixedObjectsReturnsCompleteObjectArray()
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
         * Then the result array returns four items
         * Then the first three items are objects of the given type
         * Then the first and third item are the objects identified by the given uid
         * Then the fourth item contains an array with two objects of the given type
         * Then the first object of the fourth item is the objects identified by the given uid
         * Then the second object of the fourth item is a non-persisted object of the given type
         */

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityTwo */
        $entityTwo = GeneralUtility::makeInstance(FrontendUser::class);
        $entityTwo->setEmail('entity2@example.com');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityThree */
        $entityThree = $this->frontendUserRepository->findByIdentifier(3);

        /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage $objectStorage */
        $objectStorage = GeneralUtility::makeInstance(ObjectStorage::class);
        $objectStorage->attach($entityOne);
        $objectStorage->attach($entityTwo);

        $marker = [
            'test1' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 1),
            'test2' => new ReducedObject('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', [
                'email' => $entityTwo->getEmail(),
            ]),
            'test3' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
            'test4' => new ReducedCollection([
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
                new ReducedObject('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', [
                    'email' => $entityTwo->getEmail(),
                ]),
            ])
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
        self::assertEquals($expected['test2']->getEmail(), $result['test2']->getEmail());
        self::assertEquals($expected['test3']->getUid(), $result['test3']->getUid());

        /** @var ObjectStorage $resultStorage */
        $resultStorage = $result['test4'];
        self::assertInstanceOf(ObjectStorage::class, $resultStorage);
        self::assertCount(2, $resultStorage);

        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityThree->getUid(), $resultStorage->current()->getUid());
        $resultStorage->next();
        self::assertInstanceOf(FrontendUser::class, $resultStorage->current());
        self::assertEquals($entityTwo->getEmail(), $resultStorage->current()->getEmail());
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
            'test1' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 1),
            'test2' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
            'test3' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
            'test4' => new ReducedCollection([
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
            ])
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
            'test1' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 1),
            'test2' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
            'test3' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
            'test4' => new ReducedCollection([
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
            ])
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
            'test1' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 1),
            'test2' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
            'test3' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 9999),
            'test4' => new ReducedCollection([
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 3),
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 9999),
            ])
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
            'test1' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 1),
            'test2' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 2),
            'test3' => new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 9999),
            'test4' => new ReducedCollection([
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 8888),
                new ReducedReference('TYPO3\CMS\Extbase\Domain\Model\FrontendUser', 9999),
            ])
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
    public function legacyMarkersCanStillBeExplodedInAdvancedMode(): void
    {
        //  get a value imploded by legacy
        //  read it
        //  convert it with Legecy
        //  go on

        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');

        /** @var \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $entityOne */
        $entityOne = $this->frontendUserRepository->findByIdentifier(1);

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['accelerator']['markerReducerVersion'] = 'legacy';

        $entityOneLegacy = MarkerReducer::implode(['key' => $entityOne]);

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['accelerator']['markerReducerVersion'] = 'advanced';

        $explodedEntityOne = MarkerReducer::explode($entityOneLegacy);

        self::assertSame($entityOne, $explodedEntityOne['key']);



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
