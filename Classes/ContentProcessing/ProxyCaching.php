<?php
namespace Madj2k\Accelerator\ContentProcessing;

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
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Class ProxyCaching
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */

class ProxyCaching {


    /**
     * Returns set proxy-caching setting recursively
     *
     * @param $pid
     * @return int
     */
    static function getProxyCachingSetting ($pid)
    {
        // get PageRepository and rootline
        $rootlinePages = GeneralUtility::makeInstance(RootlineUtility::class, $pid)->get();

        $status = 0;
        if (isset($rootlinePages[count($rootlinePages) - 1])) {

            // check if something is set in current page
            if ($rootlinePages[count($rootlinePages) - 1]['tx_accelerator_proxy_caching']) {
                $status = intval($rootlinePages[count($rootlinePages) - 1]['tx_accelerator_proxy_caching']);

            // else inherit
            } else {

                foreach ($rootlinePages as $page => $values) {
                    if ($values['tx_accelerator_proxy_caching'] > 0) {
                        $status = intval($values['tx_accelerator_proxy_caching']);
                        break;
                    }
                }
            }
        }

        return $status;
    }


    /**
     * Returns HMAC-value of TYPO3 instance
     *
     * @return string
     */
    static function getSiteTag()
    {
        return GeneralUtility::hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']);
    }



    /**
     * Returns HMAC-value of TYPO3 instance plus pid
     *
     * @param int $pid
     * @return string
     */
    static function getPageTag($pid = 0)
    {
        return GeneralUtility::hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] . '_' . $pid);
    }

}
