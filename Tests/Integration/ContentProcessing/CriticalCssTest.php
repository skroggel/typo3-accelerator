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
use Madj2k\Accelerator\ContentProcessing\CriticalCss;
use Madj2k\Accelerator\ContentProcessing\HtmlMinify;
use Madj2k\Accelerator\ContentProcessing\PseudoCdn;
use Madj2k\CoreExtended\Utility\FrontendSimulatorUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * CriticalCssTest
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class CriticalCssTest extends FunctionalTestCase
{

    const FIXTURE_PATH = __DIR__ . '/CriticalCssTest/Fixtures';


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
     * @var \Madj2k\Accelerator\ContentProcessing\CriticalCss
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

        $this->importDataSet(self::FIXTURE_PATH . '/Database/Global.xml');

        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

    }

    //=============================================
    /**
     * @test
     */
    public function getSettingsTakesGivenConfig()
    {

        /**
         * Scenario:
         *
         * Given a full configuration for criticalCSS is set
         * When method is called
         * Then this configuration is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check10.typoscript',
            ]
        );

        $this->subject = $this->objectManager->get(CriticalCss::class);

        $result = $this->subject->getSettings();

        self::assertEquals('1', $result['enable']);
        self::assertNotEmpty($result['filesForLayout']);
        self::assertNotEmpty($result['filesToRemoveWhenActive']);

    }

    /**
     * @test
     */
    public function getSettingsTakesFallbackIfNoConfigurationSet()
    {

        /**
         * Scenario:
         *
         * Given no configuration for criticalCSS is set
         * When method is called
         * Then the empty default configuration is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
            ]
        );

        $this->subject = $this->objectManager->get(CriticalCss::class);

        $result = $this->subject->getSettings();
        self::assertEquals('0', $result['enable']);
        self::assertEmpty($result['filesForLayout']);
        self::assertEmpty($result['filesToRemoveWhenActive']);

    }

    //=============================================

    /**
     * @test
     */
    public function getFilePathReturnsAbsolutePathForLocalFiles()
    {

        /**
         * Scenario:
         *
         * Given a relative path on the local host
         * When the method is called
         * Then an absolute path on the local host is returned
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getFilePath(
            'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/all.css'
        );

        self::assertStringStartsWith(\TYPO3\CMS\Core\Core\Environment::getPublicPath(), $result);
    }

    /**
     * @test
     */
    public function getFilePathReturnsWebPathForLocalFiles()
    {

        /**
         * Scenario:
         *
         * Given a relative path on the local host
         * Given the second parameter is set to true
         * When the method is called
         * Then a path relative to the web-dir on the local host is returned
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getFilePath(
            'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/all.css',
            true
        );

        self::assertStringStartsWith('typo3conf/ext/', $result);
    }

    /**
     * @test
     */
    public function getFilePathReturnsUrlForExternalFiles()
    {

        /**
         * Scenario:
         *
         * Given an URL on another  host
         * When the method is called
         * Then the url is returned unchanged
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getFilePath(
            'https://www.google.de/all.css'
        );

        self::assertEquals('https://www.google.de/all.css', $result);
    }

    //=============================================

    /**
     * @test
     */
    public function rebuildMediaListReplacesScreenKeyword()
    {

        /**
         * Scenario:
         *
         * Given media-list with "screen" as string
         * When the method is called
         * Then the string "print" is returned
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->rebuildMediaList('screen');

        self::assertEquals('print', $result);
    }



    /**
     * @test
     */
    public function rebuildMediaListRemovesScreenKeywordFromList()
    {

        /**
         * Scenario:
         *
         * Given media-list with "screen,print" as string
         * When the method is called
         * Then the string "print" is returned
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->rebuildMediaList('screen, print');

        self::assertEquals('print', $result);
    }

    /**
     * @test
     */
    public function rebuildMediaListDoesNotRemovePrintKeyword()
    {

        /**
         * Scenario:
         *
         * Given media-list with "print" as string
         * When the method is called
         * Then the string "print" is returned
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->rebuildMediaList('print');

        self::assertEquals('print', $result);
    }


    /**
     * @test
     */
    public function rebuildMediaListReplacesAllKeyword()
    {

        /**
         * Scenario:
         *
         * Given media-list with "all" as string
         * When the method is called
         * Then the string "print,speech" is returned
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->rebuildMediaList('all');

        self::assertEquals('print,speech', $result);
    }


    /**
     * @test
     */
    public function rebuildMediaListReplacesAllKeywordAndRemovesScreenKeyword()
    {

        /**
         * Scenario:
         *
         * Given media-list with "all,screen,print" as string
         * When the method is called
         * Then the string "print,speech" is returned without keywords doubled
         */

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->rebuildMediaList('all,screen,print');

        self::assertEquals('print,speech', $result);
    }

    //=============================================
    /**
     * @test
     */
    public function getFrontendLayoutOfPageReturnsInheritedLayout()
    {

        /**
         * Scenario:
         *
         * Given two pages, A and B
         * Given page A is the rootpage of page B
         * Given page A as an inherited layout set
         * Given page B has no layout set
         * When the method is called
         * Then the inherited layout from page A is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
            ]
        );

        $this->importDataSet(self::FIXTURE_PATH . '/Database/Check20.xml');

        FrontendSimulatorUtility::simulateFrontendEnvironment(2);

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getFrontendLayoutOfPage();

        self::assertEquals(10, $result);

    }

    /**
     * @test
     */
    public function getFrontendLayoutOfPageReturnsOwnLayout()
    {

        /**
         * Scenario:
         *
         * Given two pages, A and B
         * Given page A is the rootpage of page B
         * Given page A as an inherited layout set
         * Given page B has a layout set
         * Given page B as an inherited layout set
         * When the method is called
         * Then the normal layout from page B is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
            ]
        );

        $this->importDataSet(self::FIXTURE_PATH . '/Database/Check30.xml');

        FrontendSimulatorUtility::simulateFrontendEnvironment(2);

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getFrontendLayoutOfPage();

        self::assertEquals(50, $result);

    }

    //=============================================
    /**
     * @test
     */
    public function getCssFilesToRemoveReturnsConfiguredFiles()
    {

        /**
         * Scenario:
         *
         * Given two CSS-files are configured as to be removed when critical CSS is active
         * When the method is called
         * Then an array is returned
         * Then the array has two elements
         * Then these elements are the two defined css-files to be removed
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check100.typoscript',
            ]
        );

        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getCssFilesToRemove();

        self::assertIsArray( $result);
        self::assertCount(2,$result);
        self::assertEquals(
            'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/removeOne.css',
            $result[10]
        );
        self::assertEquals(
            'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/removeTwo.css',
            $result[20]
        );

    }

    /**
     * @test
     */
    public function getCssFilesToRemoveReturnsNoConfiguredFiles()
    {

        /**
         * Scenario:
         *
         * Given no CSS-files are configured as to be removed when critical CSS is active
         * When the method is called
         * Then an array is returned
         * Then the array is empty
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check110.typoscript',
            ]
        );

        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getCssFilesToRemove();

        self::assertIsArray( $result);
        self::assertEmpty($result);

    }

    //=============================================
    /**
     * @test
     */
    public function getCriticalCssFilesReturnsConfiguredFiles()
    {

        /**
         * Scenario:
         *
         * Given a page with layout 0
         * Given for that layout two critical css-files are defined
         * When the method is called
         * Then an array is returned
         * Then the array has two elements
         * Then these elements are the two defined critical css-files
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check40.typoscript',
            ]
        );

        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getCriticalCssFiles();

        self::assertIsArray( $result);
        self::assertCount(2,$result);
        self::assertEquals(
            'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/criticalOne.css',
            $result[10]
        );
        self::assertEquals(
            'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Global/criticalTwo.css',
            $result[20]
        );

    }

    /**
     * @test
     */
    public function getCriticalCssFilesReturnsNoConfiguredFiles()
    {

        /**
         * Scenario:
         *
         * Given a page with layout 0
         * Given for that layout no critical css-files are defined
         * When the method is called
         * Then an array is returned
         * Then the array is empty
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check50.typoscript',
            ]
        );

        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getCriticalCssFiles();

        self::assertIsArray( $result);
        self::assertEmpty($result);

    }

    //=============================================
    /**
     * @test
     */
    public function getRebasedFileContent()
    {

        /**
         * Scenario:
         *
         * Given a path to an critical css-file
         * Given that file contains relative, absolute and external paths
         * When the method is called
         * Then the relative paths are prepended with the path to the web-dir
         * Then the absolute paths are kept unchanged
         * Then the paths to external sources are kept unchanged
         */

        $filePath = 'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Files/Check90.css';

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getRebasedFileContent($filePath);
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check90.css');

        self::assertEquals($expected, $result);
    }



    //=============================================

    /**
     * @test
     */
    public function PreProcessAndProcessRewriteLinkTags()
    {

        /**
         * Scenario:
         *
         * Given criticalCss is enabled via configuration
         * Given for the current page-layout two critical-css-files are defined
         * Given four normal CSS files are defined
         * Given one of the normal CSS files is marked as to be removed when critical CSS is active
         * When the method is called
         * Then three normal CSS-Files are included in the rendered HTML via link-tag
         * Then the one normal CSS-File that is marked as to be removed is removed
         * Then the forceOnTop-setting is respected
         * Then the media-property is changed
         * Then a data-media-property is added which contains the old media-property value
         * Then the link-tag has an onLoad action
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check60.typoscript',
            ]
        );

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);

        $result = $this->getFrontendResponse(1);
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check60.html');
        self::assertStringContainsString($expected, $result->getContent());

    }

    /**
     * @test
     */
    public function PreProcessAndProcessAddCriticalCssInline()
    {

        /**
         * Scenario:
         *
         * Given criticalCss is enabled via configuration
         * Given for the current page-layout two critical Css-files are defined
         * Given four normal CSS files are defined
         * Given one of the normal CSS files is marked as to be removed when critical CSS is active
         * When the method is called
         * Then three normal CSS-Files are included in the rendered HTML via link-tag
         * Then the one normal CSS-File that is marked as to be removed is removed
         * Then the two defined critical CSS-files are included inline
         * Then the critical CSS-files are added above the link-attributes
         * Then the order of the critical CSS-files is the same as in the configuration
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check70.typoscript',
            ]
        );

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->getFrontendResponse(1);
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check70.html');

        self::assertStringContainsString($expected, $result->getContent());
    }


    /**
     * @test
     */
    public function PreProcessAndProcessDoNothingIfDisabled()
    {

        /**
         * Scenario:
         *
         * Given criticalCss is disabled via configuration
         * Given for the current page-layout two critical Css-files are defined
         * Given four normal CSS files are defined
         * Given one of the normal CSS files is marked as to be removed when critical CSS is active*
         * When the method is called
         * Then no critical CSS-files are added
         * Then all four normal CSS-Files are included in the rendered HTML via link-tag
         * Then the defined CSS is added as link-tag
         * Then the link-tags contain a rel-attribute
         * Then the link-tags contain no onLoad-Action
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check80.typoscript',
            ]
        );

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);

        $result = $this->getFrontendResponse(1);

        self::assertStringNotContainsString('@charset "UTF-8"; .critical-one {display:none}', $result->getContent());
        self::assertStringNotContainsString('@charset "UTF-8"; .critical-two {display:none}', $result->getContent());
        self::assertStringContainsString('all.css', $result->getContent());
        self::assertStringContainsString('removeOne.css', $result->getContent());
        self::assertStringContainsString('print.css', $result->getContent());
        self::assertStringContainsString('screen.css', $result->getContent());
    }

    /**
     * @test
     */
    public function PreProcessAndProcessDoNothingIfNonMatchingLayout()
    {

        /**
         * Scenario:
         *
         * Given criticalCss is enabled via configuration
         * Given there are two critical Css-files defined but not for the current layout of the page
         * Given four normal CSS files are defined
         * Given one of the normal CSS files is marked as to be removed when critical CSS is active
         * When the method is called
         * Then no critical CSS-files are added
         * Then all four normal CSS-Files are included in the rendered HTML via link-tag
         * Then the defined CSS is added as link-tag
         * Then the link-tags contain a rel-attribute
         * Then the link-tags contain no onLoad-Action
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Check120.typoscript',
            ]
        );

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);

        $result = $this->getFrontendResponse(1);

        self::assertStringNotContainsString('@charset "UTF-8"; .critical-one {display:none}', $result->getContent());
        self::assertStringNotContainsString('@charset "UTF-8"; .critical-two {display:none}', $result->getContent());
        self::assertStringContainsString('all.css', $result->getContent());
        self::assertStringContainsString('removeOne.css', $result->getContent());
        self::assertStringContainsString('print.css', $result->getContent());
        self::assertStringContainsString('screen.css', $result->getContent());
    }

    /**
     * TearDown
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }


}
