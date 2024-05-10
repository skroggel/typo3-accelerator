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
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class HtmlMinify
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
final class HtmlMinify implements MiddlewareInterface
{

    /**
     * @var array
     */
    protected array $settings = [];


    /**
     * Adds proxy caching headers
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if (!$response instanceof NullResponse) {

            // extract the content
            $body = $response->getBody();
            $body->rewind();
            $content = $response->getBody()->getContents();

            $this->loadSettings($request);

            // get minifier and process the response
            if ($this->minify($content)) {

                // push new content back into the response
                $body = new \TYPO3\CMS\Core\Http\Stream('php://temp', 'rw');
                $body->write($content);

                return $response->withBody($body);
            }
        }

        return $response;
    }


    /**
     * Replaces extension paths in content
     *
     * @param string $content content to replace
     * @return bool
     */
    public function minify(string &$content): bool
    {
        if (!$this->settings['enable']) {
            return false;
        }

        $pages = GeneralUtility::trimExplode(',', $this->settings['excludePids']);
        if ($pages) {
            $pages = array_flip($pages);
            if (isset($pages[$GLOBALS['TSFE']->page['uid']])) {
                return false;
            }
        }

        $pageTypes = GeneralUtility::trimExplode(',', $this->settings['includePageTypes']);
        $pageTypes = array_flip($pageTypes);
        if (! isset($pageTypes[$GLOBALS['TSFE']->type])) {
            return false;
        }

        $contentBefore = $content;
        $content = preg_replace(
            '%(?>[^\S ]\s*| \s{2,})(?=(?:(?:[^<]++| <(?!/?(?:textarea|pre|script)\b))*+)(?:<(?>textarea|pre|script)\b| \z))%ix',
            ' ',
            $content
        );

        return ($content != $contentBefore);
    }


    /**
     * Loads settings
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return array
     */
    public function loadSettings(ServerRequestInterface $request): array
    {
        $settings = [
            'enable' => false,
            'excludePids' => '',
            'includePageTypes' => '0'
        ];

        if (
            ($site = $request->getAttribute('site'))
            && ($siteConfiguration = $site->getConfiguration())
            && (isset($siteConfiguration['accelerator']['htmlMinifier']))
        ){
            $settings = array_merge($settings, $siteConfiguration['accelerator']['htmlMinifier'] ?? []);

        /** @deprecated  */
        } else if (is_array($GLOBALS['TYPO3_CONF_VARS']['FE']['htmlMinify'])) {
            $settings = array_merge($settings, $GLOBALS['TYPO3_CONF_VARS']['FE']['htmlMinify']);
        }

        return $this->settings = $settings;
    }
}

