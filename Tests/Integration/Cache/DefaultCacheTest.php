<?php
namespace  Madj2k\Accelerator\Tests\Integration\Cache;

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

use Madj2k\Accelerator\Cache\DefaultCache;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Madj2k\Accelerator\ContentProcessing\CriticalCss;
use Madj2k\Accelerator\ContentProcessing\HtmlMinify;
use Madj2k\Accelerator\ContentProcessing\PseudoCdn;
use Madj2k\CoreExtended\Utility\FrontendSimulatorUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * DefaultCacheTest
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class DefaultCacheTest extends FunctionalTestCase
{

    /**
     * @const
     */
    const FIXTURE_PATH = __DIR__ . '/DefaultCacheTest/Fixtures';


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
    protected $coreExtensionsToLoad = [];


    /**
     * @var \Madj2k\Accelerator\Cache\DefaultCache|null
     */
    private ?DefaultCache $subject = null;


    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager|null
     */
    private ?ObjectManager $objectManager = null;


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
            ]
        );

        FrontendSimulatorUtility::simulateFrontendEnvironment(1);

        /** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        /** @var  \Madj2k\Accelerator\Cache\DefaultCache */
        $this->subject = $this->objectManager->get(DefaultCache::class);
    }


    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function getIdentifierReturnsDefaultValue()
    {

        /**
         * Scenario:
         *
         * Given no identifier is set
         * When the method is called
         * Then the default value is returned
         */

        self::assertEquals('accelerator', $this->subject->getIdentifier());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function setIdentifierSetsValueAndGetIdentifierReturnsItUnderscored()
    {

        /**
         * Scenario:
         *
         * Given identifier as string
         * Given setIdentifier has been called before with this value
         * When the getIdentifier-method is called
         * Then it returns the set value
         * Then the returned value is underscored
         */

        $this->subject->setIdentifier('TestCase');
        self::assertEquals('test_case', $this->subject->getIdentifier());

    }


    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function getEntryIdentifierReturnsDefaultValueAsSha1()
    {

        /**
         * Scenario:
         *
         * Given no entryIdentifier is set
         * When the method is called
         * Then it returns the default value
         * Then the returned value is the sha1-value of the default-value
         */

        self::assertEquals(sha1('accelerator'), $this->subject->getEntryIdentifier());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setEntryIdentifierSetsValueAndGetEntryIdentifierReturnsValueAsSha1 ()
    {

        /**
         * Scenario:
         *
         * Given an entryIdentifier as camel-case string
         * Given setEntryIdentifier has been called before with this value
         * When the getEntryIdentifier-method is called
         * Then the returned string is the sha1-value of the given string
         */

        $this->subject->setEntryIdentifier('TestCäse');
        self::assertEquals(sha1('TestCäse'), $this->subject->getEntryIdentifier());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setEntryIdentifierSetsValueAndGetEntryIdentifierReturnsPrefixedValueAsSha1 ()
    {

        /**
         * Scenario:
         *
         * Given an entryIdentifier as camel-case string
         * Given setEntryIdentifier has been called before with this value
         * Given a request-object
         * Given that request-object has a plugin. and extension-name set
         * Given setRequest has been called before with this request-object
         * When the getEntryIdentifier-method is called
         * Then the returned string is prefixed with the extension- and plugin-name in lowerCamelCase
         * Then appended to the prefix the sha1-value of the given string is returned
         */

        $this->subject->setEntryIdentifier('TestCäse');

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setPluginName('Plugin_Test');
        $request->setControllerExtensionName('Extension_Test');

        $this->subject->setRequest($request);

        self::assertEquals('extensionTest_pluginTest_' . sha1('TestCäse'), $this->subject->getEntryIdentifier());
    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function setRequestSetsExtensionNameAndPlugin()
    {

        /**
         * Scenario:
         *
         * Given a request-object
         * Given that request-object has a plugin- and extension-name set
         * When the method is called with that request-object as parameter
         * Then getExtensionName returns the extension-name in lowerCamelCase
         * Then getPlugin returns the plugin-name in lowerCamelCase
         */

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setPluginName('Plugin_Test');
        $request->setControllerExtensionName('Extension_Test');

        $this->subject->setRequest($request);
        self::assertEquals('extensionTest', $this->subject->getExtensionName());
        self::assertEquals('pluginTest', $this->subject->getPlugin());

    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function getExtensionNameReturnsEmptyString()
    {

        /**
         * Scenario:
         *
         * Given setRequest is not called
         * When the method is called
         * Then it returns an empty string
         */

        self::assertEmpty($this->subject->getExtensionName());
    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function getPluginReturnsEmptyString()
    {

        /**
         * Scenario:
         *
         * Given setRequest is not called
         * When the method is called
         * Then it returns an empty string
         */

        self::assertEmpty($this->subject->getPlugin());
    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function getTestModeReturnsDefaultValue()
    {

        /**
         * Scenario:
         *
         * Given no testMode is set
         * When the method is called
         * Then getTestMode returns false
         */

        self::assertFalse($this->subject->getTestMode());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setTestModeSetsValueAndGetTestModeReturnsIt ()
    {

        /**
         * Scenario:
         *
         * When the method is called with parameter true
         * Then getTestMode returns true
         */

        $this->subject->setTestMode(true);
        self::assertTrue($this->subject->getTestMode());

    }


    //=============================================
    /**
     * @test
     * @throws \Exception
     */
    public function getContentReturnsNull ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to true
         * Given setContent has not been called
         * When the getContent-method is called
         * Then it returns null
         */

        $this->subject->setTestMode(true);
        self::assertNull($this->subject->getContent());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setContentSetsValueAndGetContentReturnsIt ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to true
         * Given setContent has been called with a content X as parameter
         * When the getContent-method is called
         * Then it returns the content X
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(true);
        $this->subject->setContent($data);
        self::assertEquals($data, $this->subject->getContent());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setContentSetsValueAndGetContentReturnsNullIfDeactivated ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to false
         * Given setContent has been called with a content X as parameter
         * When the getContent-method is called
         * Then it returns null
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(false);
        $this->subject->setContent($data);
        self::assertNull($this->subject->getContent());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setContentSetsValueAndGetContentReturnsNullIfLifetimeIsLimited ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to false
         * Given setContent has been called with a content X as parameter and a lifetime of 5 seconds
         * When the getContent-method is called after six seconds
         * Then it returns null
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(false);
        $this->subject->setContent($data, 5);
        sleep(6);
        self::assertNull($this->subject->getContent());

    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function hasContentReturnsFalse ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to true
         * Given setContent has not been called
         * When the hasContent-method is called
         * Then it returns false
         */

        $this->subject->setTestMode(true);
        self::assertFalse($this->subject->hasContent());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setContentSetsValueAndHasContentReturnsTrue ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to true
         * Given setContent has been called with a content X as parameter
         * When the hasContent-method is called
         * Then it returns true
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(true);
        $this->subject->setContent($data);
        self::assertTrue($this->subject->hasContent());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setContentSetsValueAndHasContentReturnsFalseIfDeactivated ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to false
         * Given setContent has been called with a content X as parameter
         * When the hasContent-method is called
         * Then it returns false
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(false);
        $this->subject->setContent($data);
        self::assertFalse($this->subject->hasContent());

    }

    //=============================================
    /**
     * @test
     * @throws \Exception
     */
    public function resolveTagReturnsIdentifier ()
    {

        /**
         * Scenario:
         *
         * When the method is called with TAG_IDENTIFIER-constant
         * Then it returns a string
         * Then the string is the camelized identifier
         */
        self::assertEquals('accelerator', $this->subject->resolveTag($this->subject::TAG_IDENTIFIER));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function resolveTagReturnsIdentifierPlusPid ()
    {

        /**
         * Scenario:
         *
         * When the method is called with TAG_IDENTIFIER_PAGE-constant
         * Then it returns a string
         * Then the string is camelized identifier plus current pid
         */
        self::assertEquals('accelerator_1', $this->subject->resolveTag($this->subject::TAG_IDENTIFIER_PAGE));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function resolveTagReturnsIdentifierPlusPlugin ()
    {

        /**
         * Scenario:
         *
         * Given a request-object
         * Given that request-object has a pluginName set
         * Given setRequest has been called before with this request-object
         * When the method is called with TAG_IDENTIFIER_PLUGIN-constant
         * Then it returns a string
         * Then the string is the camelized identifier plus plugin
         */

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setPluginName('PluginTest');
        $request->setControllerExtensionName('ExtensionTest');

        $this->subject->setRequest($request);
        self::assertEquals('accelerator_extensionTest_pluginTest', $this->subject->resolveTag($this->subject::TAG_PLUGIN));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function resolveTagReturnsIdentifierPlusPluginPlusPid ()
    {

        /**
         * Scenario:
         *
         * Given a request-object
         * Given that request-object has a pluginName set
         * Given setRequest has been called before with this request-object
         * When the method is called with TAG_IDENTIFIER_PLUGIN_PAGE-constant
         * Then it returns a string
         * Then the string is the camelized identifier plus plugin plus current pid
         */

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setPluginName('PluginTest');
        $request->setControllerExtensionName('ExtensionTest');

        $this->subject->setRequest($request);
        self::assertEquals('accelerator_extensionTest_pluginTest_1', $this->subject->resolveTag($this->subject::TAG_PLUGIN_PAGE));
    }


    /**
     * @test
     * @throws \Exception
     */
    public function resolveTagReturnsUnmodifiedTag ()
    {

        /**
         * Scenario:
         *
         * When the method is called with a custom tag
         * Then it returns a string
         * Then the string unmodified custom tag
         */
        self::assertEquals('customTag', $this->subject->resolveTag('customTag'));
    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function getTagReturnsDefaultTags ()
    {

        /**
         * Scenario:
         *
         * Given no tags have been set
         * When the method is called
         * Then it returns the two tags
         * Then the first tag is the camelized idenifier
         * Then the second tag is the camelized identifier combined with current pid
         */

        $expected = [
            0 => 'accelerator',
            1 => 'accelerator_1'
        ];

        self::assertEquals($expected, $this->subject->getTags());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getTagReturnsDefaultTagsPlusExtensionTags ()
    {

        /**
         * Scenario:
         *
         * Given no tags have been set
         * Given a request-object
         * Given that request-object has an extensionName set
         * Given setRequest has been called before with this request-object
         * When the method is called
         * Then it returns the four tags
         * Then the first tag is the camelized identifier
         * Then the second tag is the camelized identifier plus current pid
         * Then the third tag is the camelized identifier plus extensionName
         * Then the fourth tag is the camelized identifier plus extensionName plus current pid
         */

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setControllerExtensionName('myExtension');

        $expected = [
            0 => 'accelerator',
            1 => 'accelerator_1',
            2 => 'accelerator_myExtension',
            3 => 'accelerator_myExtension_1'
        ];

        $this->subject->setRequest($request);
        self::assertEquals($expected, $this->subject->getTags());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function getTagReturnsDefaultTagsPlusPluginTags ()
    {

        /**
         * Scenario:
         *
         * Given no tags have been set
         * Given a request-object
         * Given that request-object has no extensionName set
         * Given that request-object has a pluginName set
         * Given setRequest has been called before with this request-object
         * When the method is called
         * Then it returns the four tags
         * Then the first tag is the camelized idenifier
         * Then the second tag is the camelized identifier plus current pid
         * Then the third tag is the camelized idenifier plus default plus pluginName
         * Then the fourth tag is the camelized identifier plus default plus pluginName plus current pid
         */

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setPluginName('myPlugin');

        $expected = [
            0 => 'accelerator',
            1 => 'accelerator_1',
            2 => 'accelerator_default_myPlugin',
            3 => 'accelerator_default_myPlugin_1'
        ];

        $this->subject->setRequest($request);
        self::assertEquals($expected, $this->subject->getTags());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function getTagReturnsDefaultTagsPlusExtensionTagsPlusPluginTags ()
    {

        /**
         * Scenario:
         *
         * Given no tags have been set
         * Given a request-object
         * Given that request-object has an extensionName and a pluginName set
         * Given setRequest has been called before with this request-object
         * When the method is called
         * Then it returns the four tags
         * Then the first tag is the camelized idenifier
         * Then the second tag is the camelized identifier plus current pid
         * Then the third tag is the camelized idenifier plus extensionName
         * Then the fourth tag is the camelized identifier plus extensionName plus current pid
         * Then the firth tag is the camelized idenifier plus extensionName plus pluginName
         * Then the sixth tag is the camelized identifier plus extensionName plus pluginName plus current pid*
         */

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setControllerExtensionName('myExtension');
        $request->setPluginName('myPlugin');

        $expected = [
            0 => 'accelerator',
            1 => 'accelerator_1',
            2 => 'accelerator_myExtension',
            3 => 'accelerator_myExtension_1',
            4 => 'accelerator_myExtension_myPlugin',
            5 => 'accelerator_myExtension_myPlugin_1'
        ];

        $this->subject->setRequest($request);
        self::assertEquals($expected, $this->subject->getTags());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function setTagsSetsValuesAndGetTagsReturnsDefaultTagsCombinedWithValues ()
    {

        /**
         * Scenario:
         *
         * Given two tags (A and B) have been set
         * When the method is called
         * Then it returns the four tags
         * Then the first tag is the camelized idenifier
         * Then the second tag is the camelized identifier combined with current pid
         * Then the third is the tag A
         * Then the fourth is the tag B
         */

        $expected = [
            0 => 'accelerator',
            1 => 'accelerator_1',
            2 => 'sampleTagA',
            3 => 'sampleTagB',
        ];

        $this->subject->setTags(['sampleTagA', 'sampleTagB']);
        self::assertEquals($expected, $this->subject->getTags());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function setTagsSetsValuesAndGetTagsReturnsDefaultTagsPlusPluginCombinedWithValues ()
    {

        /**
         * Scenario:
         *
         * Given a request-object
         * Given that request-object has no extensionName set
         * Given that request-object has pluginName set
         * Given setRequest has been called before with this request-object
         * Given two tags (A and B) have been set
         * When the method is called
         * Then it returns the four tags
         * Then the first tag is the camelized idenifier
         * Then the second tag is the camelized identifier plus current pid
         * Then the third tag is the camelized idenifier plus default plus plugin
         * Then the fourth tag is the camelized identifier plus default plus plugin plus current pid
         * Then the fifth tag is the tag A
         * Then the sixth tag is the tag B
         */

        /** @var \TYPO3\CMS\Extbase\Mvc\Request $request */
        $request = $this->objectManager->get(Request::class);
        $request->setPluginName('My Plugin');

        $expected = [
            0 => 'accelerator',
            1 => 'accelerator_1',
            2 => 'accelerator_default_myPlugin',
            3 => 'accelerator_default_myPlugin_1',
            4 => 'sampleTagA',
            5 => 'sampleTagB',
        ];

        $this->subject->setRequest($request);
        $this->subject->setTags(['sampleTagA', 'sampleTagB']);
        self::assertEquals($expected, $this->subject->getTags());

    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function flushByTagWithDefaultTagClearsCache ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to true
         * Given setTags has not been called
         * Given setContent has been called with a content X as parameter
         * When the method is called with TAG_IDENTIFIER_PAGE-constant
         * Then getContent returns null
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(true);
        $this->subject->setContent($data);
        $this->subject->flushByTag($this->subject::TAG_IDENTIFIER_PAGE);
        self::assertNull($this->subject->getContent());

    }


    /**
     * @test
     * @throws \Exception
     */
    public function flushByTagWithCustomTagClearsCache ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to true
         * Given setTags has been called with 'testTag'
         * Given setContent has been called with a content X as parameter
         * When the method is called with TAG_IDENTIFIER_PAGE-constant
         * Then getContent returns null
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(true);
        $this->subject->setTags(['testTag']);
        $this->subject->setContent($data);
        $this->subject->flushByTag('testTag');
        self::assertNull($this->subject->getContent());
    }


    /**
     * @test
     * @throws \Exception
     */
    public function flushByTagLeavesCacheUnchanged ()
    {

        /**
         * Scenario:
         *
         * Given the testMode is set to true
         * Given setTags has been called with 'testTag'
         * Given setContent has been called with a content X as parameter
         * When the method is called with 'anotherTag'
         * Then getContent returns the cached data
         */

        $data = ['fruit' => 'apple'];

        $this->subject->setTestMode(true);
        $this->subject->setTags(['testTag']);
        $this->subject->setContent($data);
        $this->subject->flushByTag('anotherTag');
        self::assertEquals($data, $this->subject->getContent());

    }

    //=============================================

    /**
     * @test
     * @throws \Exception
     */
    public function getCacheManagerReturnsCacheManagerObject ()
    {

        /**
         * Scenario:
         *
         * When the method is called
         * Then it returns an instance of \TYPO3\CMS\Core\Cache\CacheManager
         */

        self::assertInstanceOf(CacheManager::class, $this->subject->getCacheManager());
    }

    //=============================================

    /**
     * TearDown
     */
    protected function tearDown(): void
    {
        FrontendSimulatorUtility::resetFrontendEnvironment();
        parent::tearDown();
    }


}
