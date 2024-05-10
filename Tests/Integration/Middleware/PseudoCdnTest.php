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

use Madj2k\Accelerator\Testing\FakeRequest;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Madj2k\Accelerator\Middleware\PseudoCdn;
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


    /**
     * @const
     */
    const FIXTURE_PATH = __DIR__ . '/PseudoCdnTest/Fixtures';


    /**
     * @var string[]
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/accelerator',
    ];


    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [

    ];


    /**
     * @var \Madj2k\Accelerator\Middleware\PseudoCdn|null
     */
    private ?PseudoCdn $subject = null;


    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager|null
     */
    private ?ObjectManager $objectManager = null;


    /**
     * @var array
     */
    private array $defaultSettings = [];


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

        $this->subject = $this->objectManager->get(PseudoCdn::class);
        $this->defaultSettings = [
            'enable' => false,
            'maxConnectionsPerDomain' => 4,
            'maxSubdomains' => 100,
            'search' => '/(href="|src="|srcset="|url\(\')\/?((uploads\/media|uploads\/pics|typo3temp\/compressor|typo3temp\/GB|typo3conf\/ext|fileadmin)([^"\']+))/i',
            'ignoreIfContains' => '/\.css|\.js|\.mp4|\.pdf|\?noCdn=1/',
            'baseDomain' => 'example.com',
            'protocol' => 'http://'
        ];

        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
            ],
            ['example.com' => self::FIXTURE_PATH .  '/Frontend/Configuration/Default.yaml']
        );

    }


    //=============================================

    /**
     * @test
     * @throws \Exception|\Doctrine\DBAL\Driver\Exception
     */
    public function loadSettingsLoadsConfigIfSet()
    {

        /**
         * Scenario:
         *
         * Given a configuration is set
         * When the method is called
         * Then an array is returned
         * Then the defined configuration is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::FIXTURE_PATH. '/Frontend/Configuration/Rootpage.typoscript',
            ],
            ['example.com' => self::FIXTURE_PATH .  '/Frontend/Configuration/Check10.yaml']
        );
        
        $fakeRequest = $this->objectManager->get(FakeRequest::class);
        $request = $fakeRequest->getRequestForPid(1);

        $result = $this->subject->loadSettings($request);
        $expected = array_merge ($this->defaultSettings,
            [
                'enable' => true,
                'maxConnectionsPerDomain' => 9,
                'maxSubdomains' => 99,
                'search' => '/test/i',
                'ignoreIfContains' => '/test/',
                'baseDomain' => 'test.com',
                'protocol' => 'test://'
            ]
        );

        self::assertEquals($expected, $result);
    }


    /**
     * @test
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function loadSettingsTakesFallbackIfNoConfigurationSet()
    {

        /**
         * Scenario:
         *
         * Given no configuration is set
         * When the method is called
         * Then an array is returned
         * Then the default configuration is returned
         */

        $fakeRequest = $this->objectManager->get(FakeRequest::class);
        $request = $fakeRequest->getRequestForPid(1);

        $result = $this->subject->loadSettings($request);
        self::assertEquals($this->defaultSettings, $result);
    }


   

    //=============================================

    /**
     * @test
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function addCdnDomainAddsStaticDomainToRelativePath()
    {

        /**
         * Scenario:
         *
         * Given loadSettings has been called before
         * Given a relative path
         * When the method is called
         * Then a string returned
         * Then an absolute path is returned
         * Then the absolute path uses a static domain
         */

        $fakeRequest = $this->objectManager->get(FakeRequest::class);
        $request = $fakeRequest->getRequestForPid(1);
        $this->subject->loadSettings($request);

        $relativePath = '/fileadmin/testen/test.jpg';
        $result = $this->subject->addCdnDomain($relativePath);
        $expected = 'http://static1.example.com' . $relativePath;

        self::assertEquals($expected, $result);
    }


    /**
     * @test
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function addCdnDomainAddsStaticDomainToRelativePathRespectingMaxConnectionsPerDomain()
    {

        /**
         * Scenario:
         *
         * Given loadSettings has been called before
         * Given a relative path
         * When the method is called multiple times
         * Then each time a string returned
         * Then each time an absolute path is returned
         * Then each time the absolute path uses a static domain
         * Then for every maxConnectionsPerDomain a new static domain is used
         */

        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::FIXTURE_PATH. '/Frontend/Configuration/Rootpage.typoscript',
            ],
            ['example.com' => self::FIXTURE_PATH .  '/Frontend/Configuration/Check30.yaml']
        );

        $fakeRequest = $this->objectManager->get(FakeRequest::class);
        $request = $fakeRequest->getRequestForPid(1);
        $this->subject->loadSettings($request);

        $relativePath = '/fileadmin/testen/test.jpg';
        $replacementCnt = 1;
        $domainCnt = 1;

        $result = $this->subject->addCdnDomain($relativePath, $replacementCnt, $domainCnt);
        $expected = 'http://static1.example.com' . $relativePath;
        self::assertEquals($expected, $result);

        $result = $this->subject->addCdnDomain($relativePath, $replacementCnt, $domainCnt);
        $expected = 'http://static1.example.com' . $relativePath;
        self::assertEquals($expected, $result);

        $result = $this->subject->addCdnDomain($relativePath, $replacementCnt, $domainCnt);
        $expected = 'http://static2.example.com' . $relativePath;
        self::assertEquals($expected, $result);
    }


    /**
     * @test
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function addCdnDomainAddsStaticDomainToRelativePathRespectingMaxSubdomains()
    {

        /**
         * Scenario:
         *
         * Given loadSettings has been called before
         * Given a relative path
         * When the method is called more often than maxSubdomains
         * Then the relative path is returned
         */
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::FIXTURE_PATH. '/Frontend/Configuration/Rootpage.typoscript',
            ],
            ['example.com' => self::FIXTURE_PATH .  '/Frontend/Configuration/Check30.yaml']
        );

        $fakeRequest = $this->objectManager->get(FakeRequest::class);
        $request = $fakeRequest->getRequestForPid(1);
        $this->subject->loadSettings($request);

        $relativePath = '/fileadmin/testen/test.jpg';
        $replacementCnt = 1;
        $domainCnt = 100;

        $result = $this->subject->addCdnDomain($relativePath, $replacementCnt, $domainCnt);
        $expected = $relativePath;
        self::assertEquals($expected, $result);

    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function replaceIsNotRunningIfNotEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given a string with replaceable relative paths
         * Given loadSettings has been called before
         * When this method is called
         * Then false is returned
         * Then the string is returned unchanged
         */

        $fakeRequest = $this->objectManager->get(FakeRequest::class);
        $request = $fakeRequest->getRequestForPid(1);
        $this->subject->loadSettings($request);
        
        $html = $htmlBefore = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');
        self::assertFalse($this->subject->replace($html));
        self::assertEquals($html, $htmlBefore);
    }


    /**
     * @test
     * @throws \Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function replaceReplacesLinksIfEnabled()
    {

        /**
         * Scenario:
         *
         * Given the default configuration is set
         * Given pseudoCdn has been enabled
         * Given a string with replaceable relative paths
         * Given loadSettings has been called before
         * Then true is returned
         * Then the links to static contents are replaced in the string
         * Then links to CSS-files are not replaced
         * Then links to JS-files are not replaced
         * Then links to external sites are not replaced*
         * Then normal links are not replaced
         */

        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::FIXTURE_PATH. '/Frontend/Configuration/Rootpage.typoscript',
            ],
            ['example.com' => self::FIXTURE_PATH .  '/Frontend/Configuration/Check20.yaml']
        );
        
        $fakeRequest = $this->objectManager->get(FakeRequest::class);
        $request = $fakeRequest->getRequestForPid(1);
        $this->subject->loadSettings($request);

        $html = file_get_contents(self::FIXTURE_PATH . '/Frontend/Templates/Default.html');
        $expected = file_get_contents(self::FIXTURE_PATH . '/Expected/Check20.html');
        self::assertTrue($this->subject->replace($html));
        self::assertEquals($expected, $html);
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
