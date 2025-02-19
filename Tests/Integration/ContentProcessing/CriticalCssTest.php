<?php
declare(strict_types=1);
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

use Madj2k\Accelerator\ContentProcessing\CriticalCss;
use Madj2k\Accelerator\Testing\FakeRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

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

    use FakeRequestTrait;

    /**
     * @const
     */
    const string FIXTURE_PATH = __DIR__ . '/CriticalCssTest/Fixtures';


    /**
     * @var string[]
     */
    protected array $testExtensionsToLoad = [
        'accelerator',
        'schema'
    ];


    /**
     * @var string[]
     */
    protected array $coreExtensionsToLoad = [
        'redirects',
    ];


    /**
     * @var \Madj2k\Accelerator\ContentProcessing\CriticalCss|null
     */
    private ?CriticalCss $subject = null;


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
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Rootpage.typoscript',
            ],
        );

        $this->subject = new CriticalCss();

    }

    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function isInFrontendContextReturnsFalsePerDefault()
    {

        /**
         * Scenario:
         *
         * Given a request object in backend-context
         * When method is called
         * Then false is returned
         */

        $request = $this->createServerRequest(1, 'http://www.example.com', 'GET', [], false);

        $result = $this->subject->isInFrontendContext($request);
        self::assertFalse($result);

    }


    /**
     * @test
     * @throws \Exception
     */
    public function isInFrontendContextReturnsTrueInFrontendContext()
    {

        /**
         * Scenario:
         *
         * Given a request object in frontend-context
         * When method is called
         * Then true is returned
         */

        $request = $this->createServerRequest(1, 'http://www.example.com', 'GET', [], true);

        $result = $this->subject->isInFrontendContext($request);
        self::assertTrue($result);

    }


    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function loadSettingsTakesGivenConfig()
    {

        /**
         * Scenario:
         *
         * Given a full configuration for criticalCSS is set
         * When method is called
         * Then this configuration is returned
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check10.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $result = $this->subject->loadSettings();

        self::assertTrue($result['enable']);
        self::assertNotEmpty($result['filesForLayout']);
        self::assertNotEmpty($result['filesToRemoveWhenActive']);

    }


    /**
     * @test
     * @throws \Exception
     */
    public function loadSettingsTakesFallbackIfNoConfigurationSet()
    {

        /**
         * Scenario:
         *
         * Given no configuration for criticalCSS is set
         * When method is called
         * Then the empty default configuration is returned
         */

        $result = $this->subject->loadSettings();

        self::assertFalse($result['enable']);
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

        $result = $this->subject->getFilePath(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/all.css'
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

        $result = $this->subject->getFilePath(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/all.css',
            true
        );

        self::assertStringStartsWith('/typo3conf/ext/', $result);
    }


    /**
     * @test
     */
    public function getFilePathReturnsUrlForExternalFiles()
    {

        /**
         * Scenario:
         *
         * Given a URL on another  host
         * When the method is called
         * Then the url is returned unchanged
         */

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

        $result = $this->subject->rebuildMediaList('all,screen,print');

        self::assertEquals('print,speech', $result);
    }


    //=============================================


    /**
     * @test
     * @throws \Exception
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

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check20.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);

        $this->subject = new CriticalCss();
        $result = $this->subject->getCssFilesToRemove();

        self::assertIsArray( $result);
        self::assertCount(2,$result);
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/removeOne.css',
            $result[0]
        );
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/removeTwo.css',
            $result[1]
        );
    }


    /**
     * @test
     * @throws \Exception
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

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check30.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);

        $this->subject = new CriticalCss ();

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $result = $this->subject->getCssFilesToRemove();

        self::assertIsArray( $result);
        self::assertEmpty($result);

    }


    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function getCriticalCssFilesReturnsConfiguredFilesForLayout()
    {

        /**
         * Scenario:
         *
         * Given a page with layout "home"
         * Given for that layout two critical css-files are defined
         * When the method is called
         * Then an array is returned
         * Then the array has two elements
         * Then these elements are the two defined critical css-files
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check40.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);

        $this->subject = new CriticalCss ();
        $result = $this->subject->getCriticalCssFiles();

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalOne.css',
            $result[0]
        );
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalTwo.css',
            $result[1]
        );
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getCriticalCssFilesReturnsConfiguredFilesForPath()
    {

        /**
         * Scenario:
         *
         * Given a page with path /test/me
         * Given for that path two critical css-files are defined with a preg-key
         * When the method is called
         * Then an array is returned
         * Then the array has two elements
         * Then these elements are the two defined critical css-files
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check41.php');
        $this->createServerRequest(1, 'http://www.example.com/test/me', 'GET', $additionalSiteConfig);

        $this->subject = new CriticalCss ();
        $result = $this->subject->getCriticalCssFiles();

        self::assertIsArray( $result);
        self::assertCount(2, $result);
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalOne.css',
            $result[0]
        );
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalTwo.css',
            $result[1]
        );
    }

    /**
     * @test
     * @throws \Exception
     */
    public function getCriticalCssFilesReturnsConfiguredFilesForPage()
    {

        /**
         * Scenario:
         *
         * Given a page with id = 1
         * Given for that pageId two critical css-files are defined
         * When the method is called
         * Then an array is returned
         * Then the array has two elements
         * Then these elements are the two defined critical css-files
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check42.php');
        $this->createServerRequest(1, 'http://www.example.com/', 'GET', $additionalSiteConfig);

        $this->subject = new CriticalCss ();
        $result = $this->subject->getCriticalCssFiles();

        self::assertIsArray( $result);
        self::assertCount(2, $result);
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalOne.css',
            $result[0]
        );
        self::assertEquals(
            'EXT:accelerator/Resources/Public/Tests/Fixtures/Global/criticalTwo.css',
            $result[1]
        );
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getCriticalCssFilesReturnsNoConfiguredFiles()
    {

        /**
         * Scenario:
         *
         * Given a page with layout "home"
         * Given for that layout no critical css-files are defined
         * When the method is called
         * Then an array is returned
         * Then the array is empty
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check50.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);

        $this->subject = new CriticalCss();
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
         * Given a path to a critical css-file
         * Given that file contains relative, absolute and external paths
         * When the method is called
         * Then the relative paths are prepended with the path to the web-dir
         * Then the absolute paths are kept unchanged
         * Then the paths to external sources are kept unchanged
         */

        /**
         * NOTE: The testing framework does not work in composer-mode. Because of this we have to expect paths the old way!!
         */

        $filePath = 'EXT:accelerator/Resources/Public/Tests/Fixtures/Check60.css';
        $result = $this->subject->getRebasedFileContent($filePath);
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check60.css');

        self::assertEquals($expected, $result);
    }


    //=============================================


    /**
     * @throws \Exception
     */
    #[Test] public function preProcessAndProcessRewriteLinkTags()
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
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Rootpage.typoscript',
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Check70.typoscript',
            ]
        );

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check70.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $this->subject = new CriticalCss();

        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check70.html');

        /** @var \TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest $newRequest */
        $newRequest = new InternalRequest('http://www.example.com/');
        $response = $this->executeFrontendSubRequest($newRequest);

        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        self::assertStringContainsString($expected, $content);
    }


    /**
     * @test
     */
    public function preProcessAndProcessAddCriticalCssInline()
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
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Rootpage.typoscript',
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Check80.typoscript',
            ]
        );

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check80.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $this->subject = new CriticalCss();

        $this->subject = GeneralUtility::makeInstance(CriticalCss::class);
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check80.html');

        /** @var \TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest $newRequest */
        $newRequest = new InternalRequest('http://www.example.com/');
        $response = $this->executeFrontendSubRequest($newRequest);

        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        self::assertStringContainsString($expected, $content);
    }


    /**
     * @test
     * @throws \Exception
     */
    public function preProcessAndProcessDoNothingIfDisabled()
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
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Rootpage.typoscript',
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Check90.typoscript',
            ]
        );

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check90.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $this->subject = new CriticalCss();

        /** @var \TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest $newRequest */
        $newRequest = new InternalRequest('http://www.example.com/');
        $response = $this->executeFrontendSubRequest($newRequest);

        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        self::assertStringNotContainsString('@charset "UTF-8"; .critical-one {display:none}', $content);
        self::assertStringNotContainsString('@charset "UTF-8"; .critical-two {display:none}', $content);
        self::assertStringContainsString('all.css', $content);
        self::assertStringContainsString('removeOne.css', $content);
        self::assertStringContainsString('print.css', $content);
        self::assertStringContainsString('screen.css', $content);
    }


    /**
     * @test
     * @throws \Exception
     */
    public function preProcessAndProcessDoNothingIfNonMatchingLayout()
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
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Rootpage.typoscript',
                'EXT:accelerator/Tests/Integration/ContentProcessing/CriticalCssTest/Fixtures/Frontend/Configuration/TypoScript/Check100.typoscript',
            ]
        );

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Site/Check100.php');
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $this->subject = new CriticalCss();

        /** @var \TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest $newRequest */
        $newRequest = new InternalRequest('http://www.example.com/');
        $response = $this->executeFrontendSubRequest($newRequest);

        $stream = $response->getBody();
        $stream->rewind();
        $content = $stream->getContents();

        self::assertStringNotContainsString('@charset "UTF-8"; .critical-one {display:none}', $content);
        self::assertStringNotContainsString('@charset "UTF-8"; .critical-two {display:none}', $content);
        self::assertStringContainsString('all.css', $content);
        self::assertStringContainsString('removeOne.css', $content);
        self::assertStringContainsString('print.css', $content);
        self::assertStringContainsString('screen.css', $content);
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
