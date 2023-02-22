<?php

namespace Madj2k\Accelerator\XClasses\Varnish\Utility;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;
use Psr\Log\LoggerInterface;

/**
 * Add logs to varnish extension
 */
class VarnishHttpUtility extends \Opsone\Varnish\Utility\VarnishHttpUtility
{

    /**
     * Add command to cURL Multi-Handle Queue
     *
     * @param string $method The method-name
     * @param string $url The url
     * @param string|array $header The header
     * @return void
     */
    public static function addCommand($method, $url, $header = ''): void
    {

        /** @var LoggerInterface $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $logger->debug(sprintf('Called varnish %s for %s with header %s.', $method, $url, str_replace("\n", '', print_r($header, true))));

        parent::addCommand($method, $url, $header);

    }

}
