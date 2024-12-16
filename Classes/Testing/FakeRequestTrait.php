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
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     * @param int $pid
     * @param string $url
     * @param string $method
     * @param array $additionalSiteConfig
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
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
            'SCRIPT_NAME' => '/typo3/index.php',
            'PHP_SELF' => '/typo3/index.php',
            'SCRIPT_FILENAME' => $docRoot . '/index.php',
            'PATH_TRANSLATED' => $docRoot . '/index.php',
            'QUERY_STRING' => $requestUrlParts['query'] ?? '',
            'REQUEST_URI' => $requestUrlParts['path'] . (isset($requestUrlParts['query']) ? '?' . $requestUrlParts['query'] : ''),
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
        // set up normalizedParams
        $request = new ServerRequest($url, $method, null, [], $serverParams);
        $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        // setup site configuration
        /** @var \TYPO3\CMS\Core\Configuration\SiteConfiguration $siteConfiguration */
        $siteIdentifier = $requestUrlParts['host'] ?? 'localhost';
        $siteConfiguration = GeneralUtility::makeInstance(SiteConfiguration::class);
        $siteConfiguration->createNewBasicSite($siteIdentifier, $pid, $url);

        // additional configuration added
        if (
            ($additionalSiteConfig)
            && ($siteConfigurationArray = $siteConfiguration->load($siteIdentifier))
        ){
            $siteConfigurationArray = array_merge($siteConfigurationArray, $additionalSiteConfig);
            $siteConfiguration->write($siteIdentifier, $siteConfigurationArray);
        }

        // get complete configuration and add relevant attributes to request
        /** @see \TYPO3\CMS\Frontend\Middleware\SiteResolver */
        $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);
        $routeResult = $siteMatcher->matchRequest($request);

        $request = $request->withAttribute('site', $routeResult->getSite());
        $request = $request->withAttribute('language', $routeResult->getLanguage());
        $request = $request->withAttribute('routing', $routeResult);

        if ($frontendContext) {
            $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        }

        if ($routeResult->getLanguage() instanceof SiteLanguage) {
            Locales::setSystemLocaleFromSiteLanguage($routeResult->getLanguage());
        }

        return $GLOBALS['TYPO3_REQUEST'] = $request;
    }
}
