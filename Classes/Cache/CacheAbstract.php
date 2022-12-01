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

/**
 * Class CacheAbstract
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
abstract class CacheAbstract implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * @var string Key for cache
     */
    protected $_key = 'tx_accelerator';

    /**
     * @var string Identifier for cache
     */
    protected $_identifier = 'tx_accelerator';

    /**
     * @var string Contains context mode (Production, Development...)
     */
    protected $contextMode  = '';

    /**
     * @var string Contains environment mode (FE or BE)
     */
    protected $environmentMode = '';


    /**
     * Constructor
     *
     * @param string $environmentMode
     * @param string $contextMode
     * @return void
     */
    public function __construct(string $environmentMode = '', string $contextMode = '')
    {

        if ($environmentMode) {
            $this->environmentMode = $environmentMode;
        }

        if ($contextMode) {
            $this->contextMode = $contextMode;
        }
    }

    /**
     * Returns cache identifier
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->_identifier;
    }


    /**
     * Returns cache identifier
     *
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier(string $identifier): self
    {
        $this->_identifier = sha1($identifier);
        return $this;
    }


    /**
     * Returns cached object
     *
     * @param string $identifier
     * @return mixed
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function getContent(string $identifier = '')
    {

        if ($identifier) {
            $this->setIdentifier($identifier);
        }

        // only use cache when in production
        // and when called from FE
        if (
            ($this->getContextMode() != 'Production')
            || ($this->getEnvironmentMode() != 'FE')
        ) {
            return false;
        }

        $this->getCacheManager()
            ->getCache($this->_key)
            ->get($this->getIdentifier());
    }


    /**
     * sets cached content
     *
     * @param mixed $data
     * @param array $tags
     * @param string $identifier
     * @param integer $lifetime
     * @return $this
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function setContent($data, array $tags = array(), string $identifier = '', int $lifetime = 21600): self
    {

        if ($identifier) {
            $this->setIdentifier($identifier);
        }

        // only use cache when in production
        // and when called from FE
        if (
            ($this->getContextMode() != 'Production')
            || ($this->getEnvironmentMode() != 'FE')
        ) {
            return $this;
        }

        $this->getCacheManager()
            ->getCache($this->_key)
            ->set($this->getIdentifier(), $data, $tags, $lifetime);

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
     * Function to return the current TYPO3_CONTEXT.
     *
     * @return string
     */
    protected function getContextMode(): string
    {

        if (!$this->contextMode) {
            if (getenv('TYPO3_CONTEXT')) {
                $this->contextMode = getenv('TYPO3_CONTEXT');
            }
        }

        return $this->contextMode ?: '';
    }

    /**
     * Function to return the current TYPO3_MODE.
     * This function can be mocked in unit tests to be able to test frontend behaviour.
     *
     * @return string
     * @see \TYPO3\CMS\Core\Resource\AbstractRepository
     */
    protected function getEnvironmentMode(): string
    {

        if (!$this->environmentMode) {
            if (TYPO3_MODE) {
                $this->environmentMode = TYPO3_MODE;
            }
        }

        return $this->environmentMode ?: '';
    }

}
