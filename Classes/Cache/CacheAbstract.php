<?php
namespace Madj2k\Accelerator\Cache;

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

use Madj2k\CoreExtended\Utility\GeneralUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class CacheAbstract
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
abstract class CacheAbstract implements CacheInterface, \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * @const string
     */
    const TAG_IDENTIFIER = '###ID###';


    /**
     * @const string
     */
    const TAG_IDENTIFIER_PAGE = '###ID_PA###';


    /**
     * @const string
     */
    const TAG_EXTENSION = '###ID_EXT###';


    /**
     * @const string
     */
    const TAG_EXTENSION_PAGE = '###ID_EXT_PA###';


    /**
     * @const string
     */
    const TAG_PLUGIN = '###ID_EXT_PL###';


    /**
     * @const string
     */
    const TAG_PLUGIN_PAGE = '###ID_EXT_PL_PA###';


    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Request|null
     */
    protected ?Request $request = null;


    /**
     * @var string Identifier for cache
     */
    protected string $identifier = 'accelerator';


    /**
     * @var string EntryIdentifier for cache
     */
    protected string $entryIdentifier = 'accelerator';


    /**
     * @var bool Contains test mode
     */
    protected bool $testMode = true;


    /**
     * @var array Contains relevant tags
     */
    protected array $tags = [];


    /**
     * Returns cache identifier
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return GeneralUtility::underscore($this->identifier);
    }


    /**
     * sets cache identifier
     *
     * @param string $identifier
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setIdentifier(string $identifier): CacheInterface
    {
        $this->identifier = $identifier;
        return $this;
    }


    /**
     * Returns cache entryIdentifier
     *
     * @return string
     */
    public function getEntryIdentifier(): string
    {
        $prefix = '';
        if ($this->getExtensionName()) {
            $prefix = $this->getExtensionName() . '_';
        }
        if ($this->getPlugin()) {
            $prefix .= $this->getPlugin() . '_';
        }

        return $prefix . sha1($this->entryIdentifier);
    }


    /**
     * sets cache entryIdentifier
     *
     * @param string $entryIdentifier
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setEntryIdentifier(string $entryIdentifier): CacheInterface
    {
        $this->entryIdentifier = $entryIdentifier;
        return $this;
    }


    /**
     * sets request object
     *
     * @param \TYPO3\CMS\Extbase\Mvc\Request $request
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setRequest(Request $request): CacheInterface
    {
        $this->request = $request;
        return $this;
    }


    /**
     * Returns extensionName
     *
     * @return string
     */
    public function getExtensionName(): string
    {
        if (
            ($this->request)
            && ($extensionName = $this->request->getControllerExtensionName())
        ){
            return GeneralUtility::camelize($extensionName);
        }
        return '';
    }


    /**
     * Returns plugin
     *
     * @return string
     */
    public function getPlugin(): string
    {
        if (
            ($this->request)
            && ($pluginName = $this->request->getPluginName())
        ){
            return GeneralUtility::camelize($pluginName);
        }
        return '';
    }


     /**
     * Sets the testMode
     *
     * @param bool $testMode
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setTestMode(bool $testMode): CacheInterface
    {
        $this->testMode = $testMode;
        return $this;
    }


    /**
     * Gets the testMode
     *
     * @return bool
     */
    public function getTestMode(): bool
    {
        return $this->testMode;
    }


    /**
     * Returns cached object
     *
     * @return mixed
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function getContent()
    {

        // only use cache when in production
        // and when called from FE
        if (! $this->isCacheActive()) {
            return null;
        }

        return $this->getCacheManager()
            ->getCache($this->getIdentifier())
            ->get($this->getEntryIdentifier()) ?: null;
    }


    /**
     * sets cached content
     *
     * @param mixed $data
     * @param int $lifetime
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function setContent($data, int $lifetime = 21600): CacheInterface
    {

        // only use cache when in production
        // and when called from FE
        if (! $this->isCacheActive()) {
            return $this;
        }

        $this->getCacheManager()
            ->getCache($this->getIdentifier())
            ->set(
                $this->getEntryIdentifier(),
                $data,
                $this->getTags(),
                $lifetime
            );

        return $this;
    }


    /**
     * Checks for cached content
     *
     * @return bool
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function hasContent(): bool
    {

        // only use cache when in production
        // and when called from FE
        if (! $this->isCacheActive()) {
            return false;
        }

        return $this->getCacheManager()
            ->getCache($this->getIdentifier())
            ->has($this->getEntryIdentifier());
    }


    /**
     * Return the resolved tag
     *
     * @param string $tag
     * @return string
     */
    public function resolveTag(string $tag): string
    {
        $camelizedIdentifier = GeneralUtility::camelize($this->getIdentifier(), '', '_');
        switch ($tag) {
            case self::TAG_IDENTIFIER:
                return $camelizedIdentifier;
            case self::TAG_IDENTIFIER_PAGE:
                $pid = intval($GLOBALS['TSFE']->id);
                return $camelizedIdentifier . '_' . $pid;
            case self::TAG_EXTENSION:
                return $camelizedIdentifier . '_' . ($this->getExtensionName() ?: 'default');
            case self::TAG_EXTENSION_PAGE:
                $pid = intval($GLOBALS['TSFE']->id);
                return $camelizedIdentifier . '_' . ($this->getExtensionName() ?: 'default') . '_' . $pid;
            case self::TAG_PLUGIN:
                return $camelizedIdentifier . '_' . ($this->getExtensionName() ?: 'default') . '_' . ($this->getPlugin() ?: 'default');
            case self::TAG_PLUGIN_PAGE:
                $pid = intval($GLOBALS['TSFE']->id);
                return $camelizedIdentifier . '_' . ($this->getExtensionName() ?: 'default') . '_' . ($this->getPlugin() ?: 'default') . '_' . $pid;
        }

        return $tag;
    }


    /**
     * Gets the relevant tags
     *
     * @return array
     */
    public function getTags(): array
    {
        $defaultTags = [
            $this->resolveTag(self::TAG_IDENTIFIER),
            $this->resolveTag(self::TAG_IDENTIFIER_PAGE),
        ];

        if ($this->getExtensionName()) {
            $defaultTags[] = $this->resolveTag(self::TAG_EXTENSION);
            $defaultTags[] = $this->resolveTag(self::TAG_EXTENSION_PAGE);
        }

        if ($this->getPlugin()) {
            $defaultTags[] = $this->resolveTag(self::TAG_PLUGIN);
            $defaultTags[] = $this->resolveTag(self::TAG_PLUGIN_PAGE);
        }

        return array_merge($defaultTags, $this->tags);
    }


    /**
     * Sets the relevant tags
     *
     * @param array $tags
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setTags(array $tags): CacheInterface
    {
        // add prefix
        $this->tags = $tags;
        return $this;
    }


    /**
     * Flushes cache by tag
     *
     * @param string $tag
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function flushByTag(string $tag): CacheInterface
    {
        $this->getCacheManager()->flushCachesByTag($this->resolveTag($tag));
        return $this;
    }


    /**
     * Returns cached object
     *
     * @return \TYPO3\CMS\Core\Cache\CacheManager
     */
    public function getCacheManager(): CacheManager
    {
        /** @var $cacheManager \TYPO3\CMS\Core\Cache\CacheManager */
        $cacheManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(CacheManager::class);
        return $cacheManager;
    }


    /**
     * Function to return cache mode
     * @return bool
     */
    protected function isCacheActive(): bool
    {
        if (
            (
                (Environment::getContext()->isProduction())
                && (TYPO3_MODE == 'FE')
            )
            || $this->getTestMode()
        ) {
            return true;
        }

        return false;
    }



}
