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
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;

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
     * @var \Psr\Http\Message\ServerRequestInterface|null
     */
    protected ?ServerRequestInterface $request = null;


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
        $this->request = $request;
        $response = $handler->handle($request);
        if (!$response instanceof NullResponse) {

            // extract the content
            $body = $response->getBody();
            $body->rewind();
            $content = $response->getBody()->getContents();

            $this->settings = $this->loadSettings($request);

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
        if (empty($this->settings['enable'])) {
            return false;
        }

        $pages = GeneralUtility::trimExplode(',', $this->settings['excludePids']);
        if ($pages) {
            $pages = array_flip($pages);
            if (isset($pages[$this->getPageUid()])) {
                return false;
            }
        }

        $pageTypes = GeneralUtility::trimExplode(',', $this->settings['includePageTypes']);
        $pageTypes = array_flip($pageTypes);

        if (! isset($pageTypes[$this->getPageType()])) {
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
     * @return array
     */
    public function loadSettings(): array
    {
        $settings = [
            'enable' => false,
            'excludePids' => '',
            'includePageTypes' => '0,1,7'
        ];

        if (
            ($request = $this->getRequest())
            && ($site = $request->getAttribute('site'))
            && ($siteConfiguration = $site->getConfiguration())
            && (isset($siteConfiguration['accelerator']['htmlMinifier']))
        ){
            $settings = array_merge($settings, $siteConfiguration['accelerator']['htmlMinifier']);
            $settings['enable'] = $this->resolveEnableWithVariants(
                (bool) $settings['enable'],
                $siteConfiguration['acceleratorVariants'] ?? $siteConfiguration['accelerator']['variants'] ?? null
            );

        /** @deprecated  */
        } else if (
            (isset($GLOBALS['TYPO3_CONF_VARS']['FE']['htmlMinify']))
            && (is_array($GLOBALS['TYPO3_CONF_VARS']['FE']['htmlMinify']))
        ){
            $settings = array_merge($settings, $GLOBALS['TYPO3_CONF_VARS']['FE']['htmlMinify']);
        }

        return $this->settings = $settings;
    }


    /**
     * Checks if the enable-property has variants, and takes the first variant which matches an expression.
     *
     * @param bool $enable
     * @param array|null $variants
     * @return bool
     */
    protected function resolveEnableWithVariants(bool $enable = false, ?array $variants = null): bool
    {
        if (!empty($variants)) {

            /** @var \TYPO3\CMS\Core\ExpressionLanguage\Resolver $expressionLanguageResolver */
            $expressionLanguageResolver = GeneralUtility::makeInstance(
                Resolver::class,
                'site',
                []
            );
            foreach ($variants as $variant) {
                try {
                    if (
                        ($expressionLanguageResolver->evaluate($variant['condition']))
                        && (isset($variant['htmlMinifier']['enable']))
                    ) {
                        $enable = boolval($variant['htmlMinifier']['enable']);
                    }

                } catch (SyntaxError $e) {
                    // silently fail and do not evaluate
                    // no logger here, as Site is currently cached and serialized
                }
            }
        }
        return $enable;
    }


    /**
     * Returns the page uid
     *
     * @return int
     */
    protected function getPageUid(): int {

        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();

        if ($version <= 12) {
            return $GLOBALS['TSFE']->page['uid'];
        }

        if ($request = $this->getRequest()) {
            /** @var \TYPO3\CMS\Frontend\Page\PageInformation $pageInformation */
            $pageInformation = $request->getAttribute('frontend.page.information');
            return $pageInformation->getId();
        }

        return 0;
    }


    /**
     * Returns the page type
     *
     * @return int
     */
    protected function getPageType(): int {

        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();

        if ($version <= 12) {
            return $GLOBALS['TSFE']->type;
        }

        if ($request = $this->getRequest()) {
            /** @var \TYPO3\CMS\Frontend\Page\PageInformation $pageInformation */
            $pageInformation = $request->getAttribute('frontend.page.information');
            return $pageInformation->getPageRecord()['doktype'];
        }

        return 0;
    }


    /**
     * @return \Psr\Http\Message\ServerRequestInterface|null
     */
    private function getRequest(): ?ServerRequestInterface
    {
        return $this->request ?? $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}

