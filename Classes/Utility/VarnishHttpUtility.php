<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Utility;

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

use Opsone\Varnish\Utility\VarnishGeneralUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class VarnishHttpUtility
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
if (class_exists(\Opsone\Varnish\Utility\VarnishHttpUtility::class)){
    class VarnishHttpUtility extends \Opsone\Varnish\Utility\VarnishHttpUtility
    {

        /**
         * Add command to cURL Multi-Handle Queue
         *
         * @param string $method The methodname
         * @param string $url The url
         * @param string $header The header
         *
         * @return void
         */
        public static function addCommand($method, $url, $header = ''): void
        {

            // Header is expected as array always
            /** @noinspection ArrayCastingEquivalentInspection */
            if (!is_array($header)) {
                $header = array ($header);
            }

            try {
                $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('accelerator');
                if ($extConf['proxyCachingMode'] == 'hetzner'){

                    VarnishGeneralUtility::devLog('clearCache', array('headers' => $header));

                    /**
                     * Hetzner uses the following vcl_rec (2024-12-07)
                     *
                     * sub vcl_recv {
                     *      if (req.method == "PURGE") {
                     *          if (!client.ip ~ purge) {
                     *               return(synth(405));
                     *          }
                     *
                     *          if(!req.http.x-xkey-purge){
                     *              return (purge);
                     *          }
                     *
                     *          set req.http.x-purges = xkey.purge(req.http.x-xkey-purge);
                     *          if (std.integer(req.http.x-purges,0) != 0) {
                     *              return(synth(200, req.http.x-purges + " objects purged"));
                     *          } else {
                     *              return(synth(404, "Key not found"));
                     *          }
                     *      }
                     * }
                     **/

                    // if a xkey-header is used, we add the version for hetzner
                    $xKeyUsed = false;
                    foreach ($header as $cnt => $headerString) {

                        if (strpos($headerString, 'xkey-purge:') === 0) {
                            if ($keyValueArray = GeneralUtility::trimExplode(':', $headerString)) {
                                $header[$cnt] = 'x-xkey-purge: ' . $keyValueArray[1];
                                $xKeyUsed = true;
                            }
                        }
                    }

                    // if no xKey is used, we add an xKey for the whole page
                    if (! $xKeyUsed) {
                        $header[] = 'x-xkey-purge: siteId_' . VarnishGeneralUtility::getSitename();
                    }

                    VarnishGeneralUtility::devLog('clearCache', array('headers' => $header));
                }

                parent::addCommand($method, $url, $header);

            } catch (\Exception $e) {
                // do nothing
            }

        }
    }
} else {
    class VarnishHttpUtility
    {
        // nothing to do here
    }
}
