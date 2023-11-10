<?php
namespace Madj2k\Accelerator\Tests\Integration\Middleware;

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

use Madj2k\CoreExtended\Testing\FakeRequest;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Madj2k\Accelerator\Middleware\HtmlMinify;
use Madj2k\CoreExtended\Utility\FrontendSimulatorUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * HtmlMinifyTest
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class HtmlMinifyTest extends FunctionalTestCase
{

    /**
     * @const
     */
    const FIXTURE_PATH = __DIR__ . '/HtmlMinifyTest/Fixtures';


    /**
     * @var string[]
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/core_extended',
        'typo3conf/ext/accelerator',
        'typo3conf/ext/persisted_sanitized_routing',
        'typo3conf/ext/sr_freecap',
        'typo3conf/ext/yoast_seo'
    ];


    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [
        'seo',
        'filemetadata'
    ];


    /**
     * @var \Madj2k\Accelerator\Middleware\HtmlMinify|null
     */
    private ?HtmlMinify $subject = null;


    /**
     * Setup
     * @throws \Exception
     */
    protected function setUp(): void
    {

        parent::setUp();
        $this->importDataSet(self::FIXTURE_PATH . '/Database/Global.xml');
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
            ],
            ['example.com' => self::FIXTURE_PATH .  '/Frontend/Configuration/config.yaml']
        );

        $this->subject = GeneralUtility::makeInstance(HtmlMinify::class);

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
         * Given a configuration is set
         * When the method is called
         * Then this configuration is returned
         */
        include_once(self::FIXTURE_PATH . '/Frontend/Configuration/Check10.php');

        $result = $this->subject->loadSettings();
        $expected = [
            'enable' => true,
            'excludePids' => '9999',
            'includePageTypes' => '123456'
        ];

        self::assertEquals($expected, $result);
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
         * Given no configuration is set
         * When the method is called
         * Then the default configuration is returned
         */

        $result = $this->subject->loadSettings();
        $expected = [
            'enable' => false,
            'excludePids' => '',
            'includePageTypes' => '0'
        ];

        self::assertEquals($expected, $result);
    }


    //=============================================


    /**
     * @test
     */
    public function minifyIsNotRunningIfNotEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given a string which can be minified
         * Given loadSettings has been called before
         * When the method is called
         * Then false is returned
         * Then the string is returned unchanged
         */

        $this->subject->loadSettings();

        $html = $htmlBefore = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');

        self::assertFalse($this->subject->minify($html));
        self::assertEquals($html, $htmlBefore);
    }


    /**
     * @test
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function minifyIsReducesHtmlIfEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given enable is set to true
         * Given a string which can be minified
         * Given loadSettings has been called before
         * When the method is called
         * Then line-breaks and spaces are removed from the HTML
         * Then line-breaks and spaces in textarea-tags are kept with line-breaks
         * Then line-breaks and spaces in pre-tags are kept with line-breaks
         * Then line-breaks and spaces in script-tags are kept with line-breaks
         */

        include_once(self::FIXTURE_PATH . '/Frontend/Configuration/Check30.php');
        $this->subject->loadSettings();
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $html = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check30.html');

        self::assertTrue($this->subject->minify($html));
        self::assertEquals($expected, $html);
    }


    /**
     * @test
     */
    public function processIgnoresPagesInIgnoreList()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given enable is set to true
         * Given the current pid is configured to be ignored
         * Given a string which can be minified
         * Given loadSettings has been called before
         * When the method is called
         * Then false is returned
         * Then the string is returned unchanged
         */

        include_once(self::FIXTURE_PATH . '/Frontend/Configuration/Check40.php');
        $this->subject->loadSettings();
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        $html = $htmlBefore = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');

        self::assertFalse($this->subject->minify($html));
        self::assertEquals($html, $htmlBefore);
    }


    /**
     * @test
     */
    public function processIgnoresPageTypesNotInIncludeList()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given enable is set to true
         * Given the current pageType is not configured to be included
         * Given a string which can be minified
         * Given loadSettings has been called before
         * When the method is called
         * Then false is returned
         * Then the string is returned unchanged
         */

        include_once(self::FIXTURE_PATH . '/Frontend/Configuration/Check50.php');
        $this->subject->loadSettings();
        FrontendSimulatorUtility::simulateFrontendEnvironment(1);


        $html = $htmlBefore = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');

        self::assertFalse($this->subject->minify($html));
        self::assertEquals($html, $htmlBefore);
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
