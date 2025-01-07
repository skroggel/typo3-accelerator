<?php
declare(strict_types=1);
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

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Class CriticalCss
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
final class CriticalCss
{

    /**
     * @var array
     */
    protected array $settings = [];


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->loadSettings($this->getRequest());
    }


    /**
     * Takes processed CSS files and adds critical css-files
     *
     * @param array $params
     * @param \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer
     * @return string
     */
    public function process(array &$params, \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer): string
    {

        if (
            (! $this->settings['enable'])
            || (! $criticalCssFiles = $this->getCriticalCssFiles())
        ){
            return '';
        }

        /**
         * First of all we rebuild the existing cssInclude and cssLibs in order to load this files asynchronous.
         * Since we have critical css included the latency is better than pausing rendering with synchronous loading
         */
        /** @see: PageRenderer->renderCssFiles() */
        $cssCode = [];
        foreach (['cssLibs', 'cssFiles'] as $paramKey) {
            if (is_array($params[$paramKey])) {
                foreach ($params[$paramKey] as $file => $properties) {

                    $tag = $this->buildStyleTag($file, $properties);
                    if ($properties['forceOnTop']) {
                        array_unshift($cssCode, $tag);
                    } else {
                        $cssCode[] = $tag;
                    }

                    // remove the file from further processing
                    unset($params[$paramKey][$file]);
                }
            }
        }

        // now process the critical CSS-files
        $criticalCssCode = '';
        foreach ($criticalCssFiles as $file) {

            if ($content = $this->getRebasedFileContent($this->getFilePath($file))) {

                $criticalCssCode .= '<style>'
                    . $content
                    . '</style>'
                    . LF;
            }
        }
        array_unshift($cssCode, $criticalCssCode);
        return implode(LF, $cssCode);
    }


    /**
     * Removes default CSS files conditionally
     *
     * @param array $params
     * @param \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer
     * @return bool
     */
    public function preProcess(array &$params, \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer): bool
    {

        if (
            (!$this->settings['enable'])
            || (!$this->getCriticalCssFiles())
            || (!$removeCssFiles = $this->getCssFilesToRemove())
        ) {
            return false;
        }

        if (is_array($params['cssFiles'])) {
            foreach ($removeCssFiles as $file) {
                if (isset($params['cssFiles'][$this->getFilePath($file, true)])) {
                    unset($params['cssFiles'][$this->getFilePath($file, true)]);
                }
            }
        }

        return true;
    }


    /**
     * Build tags which load CSS asynchronous
     *
     * @param string $file
     * @param array $properties
     * @return string
     */
    protected function buildStyleTag (string $file, array $properties): string {

        $file = $this->getFilePath($file, true);
        $tag = '<link'
            . ' rel="stylesheet"'
            . ' type="text/css" href="' . htmlspecialchars($file) .'"'
            . ' media="' . htmlspecialchars($this->rebuildMediaList($properties['media'] ?: 'all')) . '"'
            . ' data-media="' . $properties['media'] . '"'
            . ((! empty($properties['title'])) ? ' title="' . htmlspecialchars($properties['title']) . '"' : '')
            . ' onload="this.media=this.dataset.media; this.onload = null"'
            . ' />';
        if ($properties['allWrap']) {
            $wrapArr = explode($properties['splitChar'] ?: '|', $properties['allWrap'], 2);
            $tag = $wrapArr[0] . $tag . $wrapArr[1];
        }

        return $tag;
    }



    /**
     * Rebase relative paths in CSS
     *
     * @param string $filePath
     * @return string
     */
    public function getRebasedFileContent (string $filePath): string
    {
        // try-catch because we rely on external files here
        try {

            if ($fileContent = file_get_contents($this->getFilePath($filePath))) {
                if ($fileContent = preg_replace_callback(
                    '#url\([\'\"]?([^\)\'\"]+)[\'\"]?\)#',
                    function (array $match) use ($filePath) {

                        if (
                            ($path = $match[1])
                            && (
                                (strpos($path, '/') === 0)
                                || (strpos($path, './') === 0)
                                || (strpos($path, '../') === 0)
                            )
                        ) {
                            // prepend web-path
                            $webPath = dirname($this->getFilePath($filePath, true));

                            return 'url(/' . $webPath . '/' . $path . ')';
                        }

                        // return path unchanged
                        return 'url(' . $match[1] . ')';
                    },
                    $fileContent
                )) {
                    // return modified content
                    return $fileContent;
                }
            }

        } catch (\Exception $e) {
            // do nothing
        }

        return '';
    }


    /**
     * Returns the configured critical CSS-files for the current layout
     *
     * @return array
     */
    public function getCriticalCssFiles (): array
    {
        if (
            (! empty($this->settings['filesForPath']))
            && (! empty($this->getPathOfPage()))
        ){
            foreach ($this->settings['filesForPath'] as $path => $config) {
                if (
                    (is_array($config))
                    && (preg_match('#' . $path . '#', $this->getPathOfPage()))
                ){
                    return $config;
                }
            }
        }


        if (
            (! empty($this->settings['filesForPages']))
            && (! empty($this->getUidOfPage()))
        ){
            foreach ($this->settings['filesForPages'] as $pagesList => $config) {
                if (
                    ($pages = GeneralUtility::trimExplode(',', $pagesList))
                    && (in_array($this->getUidOfPage(), $pages))
                ){
                    return $config;
                }
            }
        }

        if (
            (! empty($this->settings['filesForLayout']))
            && (! empty($this->getLayoutOfPage()))
            && (isset($this->settings['filesForLayout'][$this->getLayoutOfPage()]))
            && (is_array($this->settings['filesForLayout'][$this->getLayoutOfPage()]))
        ){
            return $this->settings['filesForLayout'][$this->getLayoutOfPage()];
        }

        return [];
    }


    /**
     * Returns the configured CSS-files that are to be removed
     *
     * @return array
     */
    public function getCssFilesToRemove (): array
    {
        if (
            (! empty($this->settings['filesToRemoveWhenActive']))
            && (is_array($this->settings['filesToRemoveWhenActive']))
        ){
            return $this->settings['filesToRemoveWhenActive'];
        }

        return [];
    }


    /**
     * Returns the frontend layout of the current page
     *
     * @return string
     */
    public function getLayoutOfPage(): string
    {
        $pageId = $this->getUidOfPage();

        // get rootline
        $rootlinePages = GeneralUtility::makeInstance(RootlineUtility::class, $pageId)->get();
        $layout = '';

        if (is_array($rootlinePages)) {
            foreach ($rootlinePages as $key => $page) {

                // own layout-field overrides all
                if (
                    ($key == (count($rootlinePages)-1))
                    && (isset($this->settings['layoutField']))
                    && (isset($page[$this->settings['layoutField']]))
                ) {
                    return str_replace('pagets__', '', $page[$this->settings['layoutField']]);
                }

                // inherit layout from parent pages
                if (
                    ($key != (count($rootlinePages)-1))
                    && (isset($this->settings['layoutFieldNextLevel']))
                    && (isset($page[$this->settings['layoutFieldNextLevel']]))
                ) {
                    return str_replace('pagets__', '', $page[$this->settings['layoutFieldNextLevel']]);
                }
            }
        }

        return $layout;
    }


    /**
     * Returns the uri-path of the current page
     *
     * @return string
     */
    public function getPathOfPage(): string
    {
        $request = $this->getRequest();

        /** @var \TYPO3\CMS\Core\Http\Uri $uri */
        if ($uri = $request->getUri()) {
            return $uri->getPath();
        }

        return '';
    }


    /**
     * Returns the uid of the current page
     *
     * @return int
     */
    public function getUidOfPage(): int
    {
        $request = $this->getRequest();

        /** @var \TYPO3\CMS\Core\Routing\PageArguments $pageArguments */
        $pageArguments = $request->getAttribute('routing');
        if (method_exists($pageArguments, 'getPageUid')) {
            $pageId = $pageArguments->getPageId();
        } else {
            /** discouraged since TYPO3 v12 */
            $pageId = intval($GLOBALS['TSFE']->id);
        }

        return $pageId;
    }


    /**
     * Replaces "screen" and "all" from later media-attribute of style-tag
     *
     * @param string $mediaList
     * @return string
     */
    public function rebuildMediaList (string $mediaList): string
    {
        $mediaArray = GeneralUtility::trimExplode(',', $mediaList, true);
        foreach ($mediaArray as $key => $mediaString) {
            if ($mediaString == 'screen') {
                unset($mediaArray[$key]);
                $mediaArray[] = 'print';
            }
            if ($mediaString == 'all') {
                unset($mediaArray[$key]);
                array_push($mediaArray, 'print', 'speech');
            }
        }

        return implode(',', array_unique($mediaArray));
    }


    /**
     * Get the absolute file path or the file path relative to the web-dir
     *
     * @param string $file
     * @param bool $fromWebDir
     * @return string
     */
    public function getFilePath (string $file, bool $fromWebDir = false): string
    {
        if (
            (strpos($file, 'http://') === false)
            && (strpos($file, 'https://') === false)
            && (strpos($file, '//') === false)
        ) {
            if ($fromWebDir) {
                return trim(PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($file)), '/');
            }
            return GeneralUtility::getFileAbsFileName($file);
        }

        return $file;
    }


    /**
     * Loads settings
     *
     * @param \Psr\Http\Message\ServerRequestInterface|null $request
     * @return array
     */
    public function loadSettings(?ServerRequestInterface $request = null): array
    {

        $settings = [
            'enable' => false,
            'layoutField' => 'backend_layout',
            'layoutFieldNextLevel' => 'backend_layout_next_level',
            'filesForLayout' => [],
            'filesToRemoveWhenActive' => []
        ];

        if ($request) {

            /** @var \TYPO3\CMS\Core\Site\Entity\Site $site */
            if (
                ($this->isInFrontendContext($request))
                && ($site = $request->getAttribute('site'))
                && (!$site instanceof \TYPO3\CMS\Core\Site\Entity\NullSite)
            ) {
                if (
                    ($siteConfiguration = $site->getConfiguration())
                    && (isset($siteConfiguration['accelerator']['criticalCss']))
                ) {

                    $settings = array_merge($settings, $siteConfiguration['accelerator']['criticalCss']);
                    $settings['enable'] = $this->resolveEnableWithVariants(
                        (bool) $settings['enable'],
                        $siteConfiguration['acceleratorVariants'] ?? $siteConfiguration['accelerator']['variants']
                    );
                }

                // deactivate critical css if it is rendered via pageType
                if (
                    ($params = $request->getQueryParams())
                    && (
                        (
                            (isset($params['type']))
                            && ($params['type'] == '1715339215')
                        )
                        || (
                            (isset($params['no_critical_css']))
                            && ($params['no_critical_css'] == '1')
                        )
                    )
                ) {
                    $settings['enable'] = false;
                }
            }
        }

        return $this->settings = $settings;
    }


    /**
     * Checks if we are in frontend context
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return bool
     */
    public function isInFrontendContext(ServerRequestInterface $request): bool
    {

        /** @todo TYPO3_MODE can be removed when support for v10 is dropped */
        $isInFrontendContext = false;
        if (
            (defined('TYPO3_MODE'))
            && (TYPO3_MODE === 'FE')
        ){
            $isInFrontendContext = true;
        }

        if (
            (class_exists(ApplicationType::class))
            && (intval($request->getAttribute('applicationType')))
            && (ApplicationType::fromRequest($request)->isFrontend())
        ){
            $isInFrontendContext = true;
        }

        return $isInFrontendContext;
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
                        && (isset($variant['criticalCss']['enable']))
                    ){
                        $enable = boolval($variant['criticalCss']['enable']);
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
     * @return \Psr\Http\Message\ServerRequestInterface|null
     */
    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }

}
