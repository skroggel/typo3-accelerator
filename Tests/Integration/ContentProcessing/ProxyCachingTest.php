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
use Madj2k\Accelerator\ContentProcessing\ProxyCaching;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
    const BASE_PATH = __DIR__ . '/ProxyCachingTest';


    /**
     * @var string[]
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/core_extended',
        'typo3conf/ext/accelerator'
    ];


    /**
     * @var string[]
     */
    protected $coreExtensionsToLoad = [ ];


    /**
     * @var \Madj2k\Accelerator\ContentProcessing\ProxyCaching|null
     */
    protected ?ProxyCaching $subject = null;


    /**
     * Setup
     * @throws \Exception
     */
    protected function setUp(): void
    {

        parent::setUp();

        $this->importDataSet(self::BASE_PATH . '/Fixtures/Database/Global.xml');
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:accelerator/Configuration/TypoScript/setup.txt',
                self::BASE_PATH . '/Fixtures/Frontend/Configuration/Rootpage.typoscript',
            ]
        );

        $this->subject = GeneralUtility::makeInstance(ProxyCaching::class);
    }


    //=============================================

    /**
     * @test
     */
    public function getProxyCachingSettingReturnsDefaultZero()
    {

        /**
         * Scenario:
         *
         * Given no proxyCaching-configuration is set
         * When getProxyCaching is called
         * Then zero is returned
         */
        self::assertEquals(0, $this->subject::getProxyCachingSetting(1));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getProxyCachingSettingReturnsCurrentPageValue()
    {

        /**
         * Scenario:
         *
         * Given the current page has a proxyCaching-value greater than zero
         * Given the parent page has a proxyCaching-value greater than zero
         * When getProxyCaching is called
         * Then the proxyCaching-value of the current page is returned
         */
        $this->importDataSet(self::BASE_PATH .'/Fixtures/Database/Check10.xml');
        self::assertEquals(2, $this->subject::getProxyCachingSetting(101));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getProxyCachingSettingReturnsParentPageValue()
    {

        /**
         * Scenario:
         *
         * Given the current page has a proxyCaching-value equal zero
         * Given the parent page has a proxyCaching-value greater than zero
         * When getProxyCaching is called
         * Then the proxyCaching-value of the parent page is returned
         */
        $this->importDataSet(self::BASE_PATH .'/Fixtures/Database/Check20.xml');
        self::assertEquals(1, $this->subject::getProxyCachingSetting(201));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getProxyCachingSettingReturnsGrandParentPageValue()
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
        $this->importDataSet(self::BASE_PATH .'/Fixtures/Database/Check30.xml');
        self::assertEquals(1, $this->subject::getProxyCachingSetting(2001));
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
        self::assertEquals($expected, $this->subject::getSiteTag());
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
        self::assertEquals($expected, $this->subject::getPageTag(2));
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
