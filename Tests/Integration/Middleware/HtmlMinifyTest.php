<?php
declare(strict_types=1);
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

use Madj2k\Accelerator\Testing\FakeRequestTrait;
use Madj2k\Accelerator\Middleware\HtmlMinify;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

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
    use FakeRequestTrait;

    /**
     * @const
     */
    const string FIXTURE_PATH = __DIR__ . '/HtmlMinifyTest/Fixtures';


    /**
     * @var string[]
     */
    protected array $testExtensionsToLoad = [
        'accelerator',
    ];


    /**
     * @var string[]
     */
    protected array $coreExtensionsToLoad = [

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
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Global.csv');

        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                'EXT:accelerator/Configuration/TypoScript/constants.txt',
                'EXT:accelerator/Tests/Integration/Middleware/HtmlMinifyTest/Fixtures/Frontend/Configuration/Rootpage.typoscript',
            ],
        );

        $this->subject = new HtmlMinify();

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

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Check10.php');

        /** @var \Psr\Http\Message\ServerRequestInterface $request */
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
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
            'includePageTypes' => '0,1,7'
        ];

        self::assertEquals($expected, $result);
    }


    //=============================================


    /**
     * @test
     */
    public function minifyIsNotRunningIfNotExplicitlyEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given a string which can be minified
         * When the method is called
         * Then false is returned
         * Then the string is returned unchanged
         */

        $html = $htmlBefore = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');

        self::assertFalse($this->subject->minify($html));
        self::assertEquals($html, $htmlBefore);
    }


    /**
     * @test
     * @throws \Exception
     */
    public function minifyReducesHtmlIfEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given enable is set to true
         * Given a string which can be minified
         * When the method is called
         * Then line-breaks and spaces are removed from the HTML
         * Then line-breaks and spaces in textarea-tags are kept with line-breaks
         * Then line-breaks and spaces in pre-tags are kept with line-breaks
         * Then line-breaks and spaces in script-tags are kept with line-breaks
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Check20.php');

        /** @var \Psr\Http\Message\ServerRequestInterface $request */
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $this->subject->loadSettings();

        $html = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check20.html');

        self::assertTrue($this->subject->minify($html));
        self::assertEquals($expected, $html);
    }


    /**
     * @test
     * @throws \Exception
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
         * When the method is called
         * Then false is returned
         * Then the string is returned unchanged
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Check30.php');

        /** @var \Psr\Http\Message\ServerRequestInterface $request */
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $this->subject->loadSettings();

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
         * When the method is called
         * Then false is returned
         * Then the string is returned unchanged
         */

        $additionalSiteConfig = require(self::FIXTURE_PATH . '/Frontend/Configuration/Check40.php');

        /** @var \Psr\Http\Message\ServerRequestInterface $request */
        $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);
        $this->subject->loadSettings();

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
