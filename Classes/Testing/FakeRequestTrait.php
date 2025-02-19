<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Testing;

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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Class RequestTrait
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
trait FakeRequestTrait
{
    /**
     * Creates a server-request for testing purposes
     *
     * @param int $pid
     * @param string $url
     * @param string $method
     * @param array $additionalSiteConfig
     * @param bool $frontendContext
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \TYPO3\CMS\Core\Configuration\Exception\SiteConfigurationWriteException
     * @throws \TYPO3\CMS\Core\Routing\RouteNotFoundException
     */
    public function createServerRequest(
        int $pid,
        string $url,
        string $method = 'GET',
        array $additionalSiteConfig = [],
        bool $frontendContext = true
    ): ServerRequestInterface {

        /** @see \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase::createServerRequest() */
        $requestUrlParts = parse_url($url);
        $docRoot  = __DIR__;
        $serverParams = [
            'DOCUMENT_ROOT' => $docRoot,
            'HTTP_USER_AGENT' => 'TYPO3 Functional Test Request',
            'HTTP_HOST' => $requestUrlParts['host'] ?? 'localhost',
            'SERVER_NAME' => $requestUrlParts['host'] ?? 'localhost',
            'SERVER_ADDR' => '127.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'SCRIPT_FILENAME' => $docRoot . '/index.php',
            'PATH_TRANSLATED' => $docRoot . '/index.php',
            'QUERY_STRING' => $requestUrlParts['query'] ?? '',
            'REQUEST_URI' => isset($requestUrlParts['path']) ? ($requestUrlParts['path'] . (isset($requestUrlParts['query']) ? '?' . $requestUrlParts['query'] : '')) : '',
            'REQUEST_METHOD' => $method,
        ];

        // Define HTTPS and server port
        if (isset($requestUrlParts['scheme'])) {
            if ($requestUrlParts['scheme'] === 'https') {
                $serverParams['HTTPS'] = 'on';
                $serverParams['SERVER_PORT'] = '443';
            } else {
                $serverParams['SERVER_PORT'] = '80';
            }
        }

        // Define a port if used in the URL
        if (isset($requestUrlParts['port'])) {
            $serverParams['SERVER_PORT'] = $requestUrlParts['port'];
        }

        $request = new ServerRequest($url, $method, null, [], $serverParams);

        // setup site configuration
        $request = $this->addSiteConfiguration ($pid, $url, $request, $additionalSiteConfig);
        $request = $this->addSiteRoutingAndLanguage($request);
        $request = $this->addPageInformation($pid, $request);

        // set up normalizedParams
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        if ($frontendContext) {
            $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        } else {
            $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        }

        return $GLOBALS['TYPO3_REQUEST'] = $request;
    }


    /**
     * Adds page information to request
     *
     * @param int $pid
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \Psr\Http\Message\ServerRequestInterface
     * @see \TYPO3\CMS\Frontend\Middleware\SiteResolver
     */
    private function addPageInformation(int $pid, ServerRequestInterface $request): ServerRequestInterface
    {
        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();

        if ($version >= 13 ){

            /** @var \TYPO3\CMS\Frontend\Page\PageInformation $pageInformation */
            $pageInformation = GeneralUtility::makeInstance(PageInformation::class);
            $pageInformation->setId($pid);
            $pageInformation->setPageRecord(BackendUtility::getRecord('pages', $pid));

            $request = $request->withAttribute('frontend.page.information', $pageInformation);
        }

        return $request;
    }


    /**
     * Adds site, routing and language to request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \TYPO3\CMS\Core\Routing\RouteNotFoundException
     * @see \TYPO3\CMS\Frontend\Middleware\SiteResolver
     */
    private function addSiteRoutingAndLanguage(ServerRequestInterface $request): ServerRequestInterface
    {
        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();

        /** @var \TYPO3\CMS\Core\Routing\SiteMatcher $siteMatcher */
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);

        /** @var \TYPO3\CMS\Core\Routing\RouteResultInterface $routeResult */
        $routeResult = $siteMatcher->matchRequest($request);

        $request = $request->withAttribute('site', $routeResult->getSite());
        $request = $request->withAttribute('language', $routeResult->getLanguage());
        $request = $request->withAttribute('routing', $routeResult);

        if ($version >= 13 ){

            /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
            $site = $routeResult->getSite();

            /** @var \TYPO3\CMS\Core\Routing\PageArguments $pageArguments */

            $pageArguments = $site->getRouter()->matchRequest($request, $routeResult);
            $request = $request->withAttribute('routing', $pageArguments);
        }

        if ($routeResult->getLanguage() instanceof SiteLanguage) {
            Locales::setSystemLocaleFromSiteLanguage($routeResult->getLanguage());
        }

        return $GLOBALS['TYPO3_REQUEST'] = $request;
    }


    /**
     * Adds site configuration to request
     *
     * @param int $pid
     * @param string $url
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param array $additionalSiteConfig
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \TYPO3\CMS\Core\Configuration\Exception\SiteConfigurationWriteException
     */
    private function addSiteConfiguration (
        int $pid,
        string $url,
        ServerRequestInterface $request,
        array $additionalSiteConfig = []

    ): ServerRequestInterface {

        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();

        $requestUrlParts = parse_url($url);
        $siteIdentifier = $requestUrlParts['host'] ?? 'localhost';

        /** @var \TYPO3\CMS\Core\Configuration\SiteConfiguration $siteConfiguration */
        $siteConfiguration = GeneralUtility::makeInstance(SiteConfiguration::class);

        // additional configuration added
        /** @todo remove if support for v12 and below is removed */
        if ($version <= 12) {

            $siteConfiguration->createNewBasicSite($siteIdentifier, $pid, $url);
            if (
                ($additionalSiteConfig)
                && ($siteConfigurationArray = $siteConfiguration->load($siteIdentifier))
            ){
                $siteConfigurationArray = array_merge($siteConfigurationArray, $additionalSiteConfig);
                $siteConfiguration->write($siteIdentifier, $siteConfigurationArray);
            }

        } else {

            $siteWriter = GeneralUtility::makeInstance(SiteWriter::class);
            $siteWriter->createNewBasicSite($siteIdentifier, $pid, $url);

            if (
                ($additionalSiteConfig)
                && ($siteConfigurationArray = $siteConfiguration->load($siteIdentifier))
            ){
                $siteConfigurationArray = array_merge($siteConfigurationArray, $additionalSiteConfig);
                $siteWriter = GeneralUtility::makeInstance(SiteWriter::class);
                $siteWriter->write($siteIdentifier, $siteConfigurationArray);
            }
        }

        return $request;
    }
}
