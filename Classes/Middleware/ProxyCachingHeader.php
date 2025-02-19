<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Middleware;

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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Class ProxyCachingHeader
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
final class ProxyCachingHeader implements MiddlewareInterface
{

    /**
     * Adds proxy caching header
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$response instanceof NullResponse) {

            // if xkey is already added by varnish we don't have to take care of this!
            $sendXKeyTags = true;
            if (ExtensionManagementUtility::isLoaded('varnish')) {
                $varnishExtConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('varnish');
                if ($varnishExtConf['sendXkeyTags']) {
                    $sendXKeyTags = false;
                }
            }

            $pid = intval($GLOBALS['TSFE']->id);
            $response = $response->withHeader('X-TYPO3-ProxyCaching', (string)$this->getProxyCachingSettingForPid($pid));
            if ($sendXKeyTags) {
                $response = $response->withHeader('xkey', $this->getSiteTag($request) . ' ' . $this->getPageTag($request, $pid));
            }
        }

        return $response;
    }


    /**
     * Returns set proxy-caching setting recursively
     *
     * @param int $pid
     * @return int
     * @throws \Doctrine\DBAL\Exception
     */
    public function getProxyCachingSettingForPid(int $pid): int
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
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return string
     */
    public function getSiteTag(ServerRequestInterface $request): string
    {
        if (
            ($site = $request->getAttribute('site'))
            && ($siteConfiguration = $site->getConfiguration())
        ){
        //    return GeneralUtility::hmac($siteConfiguration['websiteTitle']);
        }

        /** @todo remove if support for v12 and below is dropped */
        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();
        if ($version <= 12) {
            /** @deprecated but still used by \Opsone\Varnish\Utility\VarnishGeneralUtility::getSitename()  */
            return GeneralUtility::hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']);
        }

        /** @deprecated but still used by \Opsone\Varnish\Utility\VarnishGeneralUtility::getSitename()  */
        /** @var \TYPO3\CMS\Core\Crypto\HashService $hashService */
        $hashService = GeneralUtility::makeInstance(HashService::class);
        return $hashService->hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'], 'proxyCaching');
    }


    /**
     * Returns HMAC-value of TYPO3 instance plus pid
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param int $pid
     * @return string
     */
    public function getPageTag(ServerRequestInterface $request, int $pid = 0): string
    {
        if (
            ($site = $request->getAttribute('site'))
            && ($siteConfiguration = $site->getConfiguration())
        ){
        //     return GeneralUtility::hmac($siteConfiguration['websiteTitle']) . '_' . $pid;
        }

        /** @todo remove if support for v12 and below is dropped */
        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();
        if ($version <= 12) {
            /** @deprecated but still used by \Opsone\Varnish\Utility\VarnishGeneralUtility::getSitename()  */
            return GeneralUtility::hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']). '_' . $pid;
        }

        /** @deprecated but still used by \Opsone\Varnish\Utility\VarnishGeneralUtility::getSitename()  */
        /** @var \TYPO3\CMS\Core\Crypto\HashService $hashService */
        $hashService = GeneralUtility::makeInstance(HashService::class);
        return $hashService->hmac($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'], 'proxyCaching'). '_' . $pid;
    }
}

