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

use Madj2k\Accelerator\Middleware\PseudoCdn;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Madj2k\Accelerator\Middleware\ProxyCachingHeader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

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


    /**
     * @const
     */
    const FIXTURE_PATH = __DIR__ . '/ProxyCachingTest/Fixtures';


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

        $this->importDataSet(self::FIXTURE_PATH . '/Database/Global.xml');
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.typoscript',
                'EXT:accelerator/Configuration/TypoScript/constants.typoscript',
                self::FIXTURE_PATH . '/Frontend/Configuration/Rootpage.typoscript',
            ],
            ['example.com' => self::FIXTURE_PATH .  '/Frontend/Configuration/config.yaml']
        );

        $this->subject = GeneralUtility::makeInstance(ProxyCachingHeader::class);


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
        $this->importDataSet(self::FIXTURE_PATH . '/Database/Check10.xml');
        self::assertEquals(2, $this->subject->getProxyCachingSettingForPid(101));
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
        $this->importDataSet(self::FIXTURE_PATH . '//Database/Check20.xml');
        self::assertEquals(1, $this->subject->getProxyCachingSettingForPid(201));
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
        $this->importDataSet(self::FIXTURE_PATH . '/Database/Check30.xml');
        self::assertEquals(1, $this->subject->getProxyCachingSettingForPid(2001));
    }


    //=============================================


    /**
     * @test
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

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = 'test.com';
        $expected = GeneralUtility::hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']);
        self::assertEquals($expected, $this->subject->getSiteTag());
    }


    //=============================================


    /**
     * @test
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

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = 'test.com';
        $expected = GeneralUtility::hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '_' . 2);
        self::assertEquals($expected, $this->subject->getPageTag(2));
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
