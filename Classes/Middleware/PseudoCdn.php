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

use Doctrine\DBAL\Driver\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PseudoCdn
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
final class PseudoCdn implements MiddlewareInterface
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

            try {

                $this->loadSettings($request);

                // extract the content
                $body = $response->getBody();
                $body->rewind();
                $content = $response->getBody()->getContents();

                // get CDN and process the response
                if ($this->replace($content)) {

                    // push new content back into the response
                    $body = new \TYPO3\CMS\Core\Http\Stream('php://temp', 'rw');
                    $body->write($content);

                    return $response->withBody($body);
                }

            } catch (Exception $e) {
                // do nothing
            }
        }

        return $response;
    }


    /**
     * Replace relative paths in given content
     *
     * @param string $content
     * @return bool
     */
    public function replace(string &$content): bool
    {

        // check if enabled
        if (!$this->settings['enable']) {
            return false;
        }

        // Replace content
        $object = $this;
        $config = $this->settings;
        $replacementCnt = $domainCnt = 1;
        $contentBefore = $content;
        $callback = function ($matches) use ($object, $config, &$replacementCnt, &$domainCnt) {

            if ($config['ignoreIfContains']) {
                if (preg_match($config['ignoreIfContains'], $matches[2])) {
                    return $matches[1] . $matches[2];
                }
            }

            return $matches[1] . $object->addCdnDomain($matches[2], $replacementCnt, $domainCnt);
        };

        $content = preg_replace_callback($this->settings['search'], $callback, $content);
        return ($contentBefore != $content);
    }


    /**
     * Adds static-domain to given relative path
     *
     * @param string $relativePath
     * @param int $replacementCnt
     * @param int $domainCnt
     * @return string
     */
    public function addCdnDomain(string $relativePath, int &$replacementCnt = 1, int &$domainCnt = 1): string
    {
        // no replacement if no static domains left
        if ($domainCnt > intval($this->settings['maxSubdomains'])) {
            return $relativePath;
        }

        // check if counters have reached the maximum and increment it
        if ($replacementCnt > intval($this->settings['maxConnectionsPerDomain'])) {
            if ($domainCnt < intval($this->settings['maxSubdomains'])) {
                $domainCnt++;
            }
            $replacementCnt = 1;
        }

        // cut of leading backslash
        if (strpos($relativePath, '/') === 0) {
            $relativePath = substr($relativePath, 1);
        }

        // build new subdomain
        $domain = 'static' . $domainCnt . '.' . $this->settings['baseDomain'];

        // Add one to counter
        $replacementCnt++;

        // add domain to url
        return $this->settings['protocol'] . $domain . '/' . $relativePath;
    }


    /**
     * Loads settings
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function loadSettings(ServerRequestInterface $request): array
    {

        /** @var array $serverParams */
        $serverParams = $request->getServerParams();

        /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
        $site = $request->getAttribute('site');

        $domainParts = explode('.', $site->getBase()->getHost());
        $baseDomain = $domainParts[count($domainParts) - 2] . '.' . $domainParts[count($domainParts) - 1];

        $settings = [
            'enable' => false,
            'maxConnectionsPerDomain' => 4,
            'maxSubdomains' => 100,
            'search' => '/(href="|src="|srcset="|url\(\')\/?((uploads\/media|uploads\/pics|typo3temp\/compressor|typo3temp\/GB|typo3conf\/ext|fileadmin)([^"\']+))/i',
            'ignoreIfContains' => '/\.css|\.js|\.mp4|\.pdf|\?noCdn=1/',
            'baseDomain' => $baseDomain,
            'protocol' => (($serverParams['HTTPS']) || ($serverParams['SERVER_PORT'] == '443')) ? 'https://' : 'http://'
        ];

        if (
            ($site = $request->getAttribute('site'))
            && ($siteConfiguration = $site->getConfiguration())
            && (isset($siteConfiguration['accelerator']['pseudoCdn']))
        ) {

            $settings = array_merge($settings, $siteConfiguration['accelerator']['pseudoCdn'] ?? []);
            $settings['enable'] = $this->resolveEnableWithVariants(
                $settings['enable'],
                $siteConfiguration['acceleratorVariants']
            );

            /** @deprecated */
        } else if (is_array($GLOBALS['TYPO3_CONF_VARS']['FE']['pseudoCdn'])) {
            $settings = array_merge($settings, $GLOBALS['TYPO3_CONF_VARS']['FE']['pseudoCdn']);

            $rootPage = $site->getRootPageId();
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
            $pageSwitch = $queryBuilder
                ->select('tx_accelerator_pseudo_cdn')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($rootPage))
                )
                ->execute()
                ->fetchOne();

            // check for site-specific override in rootPage
            if ($pageSwitch == 1) {
                $settings['enable'] = true;
            } else if ($pageSwitch == 2) {
                $settings['enable'] = false;
            }
        }

        return $this->settings = $settings;
    }


    /**
     * Checks if the enable-property has variants, and takes the first variant which matches an expression.
     *
     * @param int $enable
     * @param array|null $variants
     * @return int
     */
    protected function resolveEnableWithVariants(int $enable = 0, ?array $variants = null): int
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
                        && (isset($variant['pseudoCdn']['enable']))
                    ) {
                        $enable = intval($variant['pseudoCdn']['enable']);
                        break;
                    }
                } catch (SyntaxError $e) {
                    // silently fail and do not evaluate
                    // no logger here, as Site is currently cached and serialized
                }
            }
        }
        return $enable;
    }
}
