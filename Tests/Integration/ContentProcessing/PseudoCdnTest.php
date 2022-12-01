<?php
namespace Madj2k\Accelerator\Tests\Integration\ContentProcessing;

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

use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Madj2k\Accelerator\ContentProcessing\PseudoCdn;
use Madj2k\CoreExtended\Utility\FrontendSimulatorUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * PseudoCdnTest
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PseudoCdnTest extends FunctionalTestCase
{

    const BASE_PATH = __DIR__ . '/PseudoCdnTest';


    /**
     * @var string[]
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/core_extended',
        'typo3conf/ext/accelerator',
    ];

    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [ ];


    /**
     * @var \Madj2k\Accelerator\ContentProcessing\PseudoCdn
     */
    private $subject;


    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    private $objectManager;



    /**
     * Setup
     * @throws \Exception
     */
    protected function setUp(): void
    {

        parent::setUp();

        $this->importDataSet(self::BASE_PATH . '/Fixtures/Database/Global.xml');

        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

    }



    //=============================================

    /**
     * @test
     */
    public function getSettingsTakesOldConfigIfSet()
    {

        /**
         * Scenario:
         *
         * Given the configuration is set via config.tx_accelerator_cdn
         * When getConfiguration is called
         * Then this configuration is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Rootpage.typoscript',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Check10.typoscript',
            ]
        );

        // init frontend (needed in test-context)
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $this->subject = $this->objectManager->get(PseudoCdn::class);

        $result = $this->subject->getSettings();
        self::assertEquals('Old-Setup-Test', $result['ignoreIfContains']);
    }



    /**
     * @test
     */
    public function getSettingsTakesNewConfigIfNoOldIsSet()
    {

        /**
         * Scenario:
         *
         * Given the configuration is set via plugin.tx_accelerator.settings.cdn
         * When getConfiguration is called
         * Then this configuration is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Rootpage.typoscript',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Check20.typoscript',
            ]
        );

        // init frontend (needed in test-context)
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $this->subject = $this->objectManager->get(PseudoCdn::class);

        $result = $this->subject->getSettings();
        self::assertEquals('New-Setup-Test', $result['ignoreIfContains']);
    }

    /**
     * @test
     */
    public function getSettingsTakesFallbackIfNoConfigurationSet()
    {

        /**
         * Scenario:
         *
         * Given no configuration is set
         * When getConfiguration is called
         * Then the default configuration is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Rootpage.typoscript',
            ]
        );

        // init frontend (needed in test-context)
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $this->subject = $this->objectManager->get(PseudoCdn::class);

        $result = $this->subject->getSettings();
        self::assertEquals('/\.css|\.js|\?noCdn=1/', $result['ignoreIfContains']);
    }

    //=============================================

    /**
     * @test
     */
    public function processIsNotRunningIfNotEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given enable is set to false
         * When process is called for an HTML
         * Then the HTML is returned unchanged
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Rootpage.typoscript',
            ]
        );

        // init frontend (needed in test-context)
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);
        $this->subject = $this->objectManager->get(PseudoCdn::class);

        $html = file_get_contents(self::BASE_PATH . '/Fixtures/Frontend/Templates/Default.html');
        self::assertEquals($html, $this->subject->process($html));
    }

    /**
     * @test
     */
    public function processReplacesLinksIfEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given enable is set to true
         * When process is called for an HTML
         * Then the links to static contents are replaced in the HTML
         * Then links to CSS- files are not replaced
         * Then links to JS- files are not replaced
         * Then normal links are not replaced
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Rootpage.typoscript',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Check30.typoscript',
            ]
        );

        // init frontend (needed in test-context)
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);
        $this->subject = $this->objectManager->get(PseudoCdn::class);

        $html = file_get_contents(self::BASE_PATH . '/Fixtures/Frontend/Templates/Default.html');
        $expected = file_get_contents(self::BASE_PATH . '/Fixtures/Expected/Check30.html');
        self::assertEquals($expected, $this->subject->process($html));
    }



    /**
     * TearDown
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }


}
