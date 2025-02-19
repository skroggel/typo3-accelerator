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

use Madj2k\Accelerator\Middleware\ProxyCachingHeader;
use Madj2k\Accelerator\Testing\FakeRequestTrait;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * ProxyCachingTest
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ProxyCachingTest extends FunctionalTestCase
{

    use FakeRequestTrait;

    /**
     * @const
     */
    const string FIXTURE_PATH = __DIR__ . '/ProxyCachingTest/Fixtures';


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
     * @var \Madj2k\Accelerator\Middleware\ProxyCachingHeader|null
     */
    protected ?ProxyCachingHeader $subject = null;


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
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                'EXT:accelerator/Tests/Integration/Middleware/PseudoCdnTest/Fixtures/Frontend/Configuration/Rootpage.typoscript',
            ],
        );

        $this->subject = new ProxyCachingHeader();

    }


    //=============================================

    /**
     * @test
     */
    public function getProxyCachingSettingForPidReturnsDefaultZero()
    {

        /**
         * Scenario:
         *
         * Given no proxyCaching-configuration is set
         * When the method is called
         * Then zero is returned
         */
        self::assertEquals(0, $this->subject->getProxyCachingSettingForPid(1));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getProxyCachingSettingForPidReturnsCurrentPageValue()
    {

        /**
         * Scenario:
         *
         * Given the current page has a proxyCaching-value greater than zero
         * Given the parent page has a proxyCaching-value greater than zero
         * When then the method is called
         * Then the proxyCaching-value of the current page is returned
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check10.csv');
        self::assertEquals(2, $this->subject->getProxyCachingSettingForPid(11));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getProxyCachingSettingForPidReturnsParentPageValue()
    {

        /**
         * Scenario:
         *
         * Given the current page has a proxyCaching-value equal zero
         * Given the parent page has a proxyCaching-value greater than zero
         * When getProxyCaching is called
         * Then the proxyCaching-value of the parent page is returned
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check20.csv');
        self::assertEquals(1, $this->subject->getProxyCachingSettingForPid(21));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getProxyCachingSettingForPidReturnsGrandParentPageValue()
    {

        /**
         * Scenario:
         *
         * Given the current page has a proxyCaching-value equal zero
         * Given the parent page has a proxyCaching-value equal zero
         * Given the parent page of the parent page has a proxyCaching-value greater than zero
         * When getProxyCaching is called
         * Then the proxyCaching-value of the parent page of the parent page is returned
         */
        $this->importCSVDataSet(self::FIXTURE_PATH . '/Database/Check30.csv');
        self::assertEquals(1, $this->subject->getProxyCachingSettingForPid(32));
    }


    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function getSiteTagReturnsHmacValueOfTypo3Instance()
    {

        /**
         * Scenario:
         *
         * Given a sitename is set via global configuration
         * When getSiteTag is called
         * Then the hmac-value of this sitename is returned
         */
        $additionalSiteConfig = ['websiteTitle' => 'Test Test'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = 'Test Test';

        /** @var \Psr\Http\Message\ServerRequestInterface $request */
        $request = $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);

        /** @var \TYPO3\CMS\Core\Crypto\HashService $hashService */
        $hashService = GeneralUtility::makeInstance(HashService::class);
        $expected = $hashService->hmac('Test Test', 'proxyCaching');
        self::assertEquals($expected, $this->subject->getSiteTag($request));
    }


    //=============================================


    /**
     * @test
     * @throws \Exception
     */
    public function getPageTagReturnsHmacValueOfSitenamePlusPid()
    {

        /**
         * Scenario:
         *
         * Given a sitename is set via global configuration
         * When getTag is called with an pid
         * Then the hmac-value of this sitename plus the given pid is returned
         */

        $additionalSiteConfig = ['websiteTitle' => 'Test Test'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = 'Test Test';

        /** @var \Psr\Http\Message\ServerRequestInterface $request */
        $request = $this->createServerRequest(1, 'http://www.example.com', 'GET', $additionalSiteConfig);

        /** @var \TYPO3\CMS\Core\Crypto\HashService $hashService */
        $hashService = GeneralUtility::makeInstance(HashService::class);
        $expected = $hashService->hmac('Test Test', 'proxyCaching')  . '_' . 2;
        self::assertEquals($expected, $this->subject->getPageTag($request, 2));
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
