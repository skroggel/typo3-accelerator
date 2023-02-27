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

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Extbase\Mvc\Request;

/**
 * Class CacheInterface
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
interface CacheInterface
{

    /**
     * Returns cache identifier
     *
     * @return string
     */
    public function getIdentifier(): string;


    /**
     * sets cache identifier
     *
     * @param string $identifier
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setIdentifier(string $identifier): CacheInterface;


    /**
     * Returns cache entryIdentifier
     *
     * @return string
     */
    public function getEntryIdentifier(): string;


    /**
     * sets cache entryIdentifier
     *
     * @param string $entryIdentifier
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setEntryIdentifier(string $entryIdentifier): CacheInterface;


    /**
     * sets request object
     *
     * @param \TYPO3\CMS\Extbase\Mvc\Request $request
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setRequest(Request $request): CacheInterface;


    /**
     * Returns extensionName
     *
     * @return string
     */
    public function getExtensionName(): string;


    /**
     * Returns plugin
     *
     * @return string
     */
    public function getPlugin(): string;


    /**
     * Sets the testMode
     *
     * @param bool $testMode
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setTestMode(bool $testMode): CacheInterface;


    /**
     * Gets the testMode
     *
     * @return bool
     */
    public function getTestMode(): bool;


    /**
     * Returns cached object
     *
     * @return mixed
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function getContent();


    /**
     * sets cached content
     *
     * @param mixed $data
     * @param int $lifetime
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function setContent($data, int $lifetime = 21600): CacheInterface;


    /**
     * Checks for cached content
     *
     * @return bool
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function hasContent(): bool;


    /**
     * Return the resolved tag
     *
     * @param string $tag
     * @return string
     */
    public function resolveTag(string $tag): string;


    /**
     * Gets the relevant tags
     *
     * @return array
     */
    public function getTags(): array;


    /**
     * Sets the relevant tags
     *
     * @param array $tags
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function setTags(array $tags): CacheInterface;


    /**
     * Flushes cache by tag
     *
     * @param string $tag
     * @return \Madj2k\Accelerator\Cache\CacheInterface
     */
    public function flushByTag(string $tag): CacheInterface;


    /**
     * Returns cached object
     *
     * @return \TYPO3\CMS\Core\Cache\CacheManager
     */
    public function getCacheManager(): CacheManager;

}
