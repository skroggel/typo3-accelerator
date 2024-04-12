<?php
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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;


/**
 * Class FakeRequest
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_CoreExtended
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @todo write a test for it
 */
class FakeRequest
{

    /**
     * @var \TYPO3\CMS\Core\Context\Context
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected ?Context $context = null;


    /**
     * @var \TYPO3\CMS\Core\Routing\SiteMatcher
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected ?SiteMatcher $siteMatcher = null;


    /**
     * @var \TYPO3\CMS\Core\Site\SiteFinder
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected ?SiteFinder $siteFinder = null;


    /**
     * @param \TYPO3\CMS\Core\Context\Context $context
     */
    public function injectContext(Context $context)
    {
        $this->context = $context;
    }


    /**
     * @param \TYPO3\CMS\Core\Routing\SiteMatcher $siteMatcher
     */
    public function injectSiteMatcher(SiteMatcher $siteMatcher)
    {
        $this->siteMatcher = $siteMatcher;
    }


    /**
     * @param \TYPO3\CMS\Core\Site\SiteFinder $siteFinder
     */
    public function injectSiteFinder(SiteFinder $siteFinder)
    {
        $this->siteFinder = $siteFinder;
    }


    /**
     * @param int $pid
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    public function getRequestForPid(int $pid): ServerRequestInterface
    {

        /** @see \TYPO3\CMS\Backend\Command\ResetPasswordCommand::createFakeWebRequest  */
        /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
        $this->siteFinder->getAllSites(false);
        $site = $this->siteFinder->getSiteByPageId($pid);

        /** @var \TYPO3\CMS\Core\Http\Uri $uri */
        $uri = new Uri((string) $site->getBase());

        /** @var $request \TYPO3\CMS\Core\Http\ServerRequest */
        $request = new ServerRequest(
            $uri,
            'GET',
            'php://input',
            [],
            [
                'HTTP_HOST' => $uri->getHost(),
                'SERVER_NAME' => $uri->getHost(),
                'HTTPS' => $uri->getScheme() === 'https',
                'SCRIPT_FILENAME' => __FILE__,
                'SCRIPT_NAME' => rtrim($uri->getPath(), '/') . '/'
            ]
        );

        /** @see \TYPO3\CMS\Frontend\Middleware\SiteResolver */
        $routeResult = $this->siteMatcher->matchRequest($request);
        $request = $request->withAttribute('site', $routeResult->getSite());
        $request = $request->withAttribute('language', $routeResult->getLanguage());
        $request = $request->withAttribute('routing', $routeResult);
        if ($routeResult->getLanguage() instanceof SiteLanguage) {
            Locales::setSystemLocaleFromSiteLanguage($routeResult->getLanguage());
        }

        return $request;
    }

}
