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
            . ($properties['title'] ? ' title="' . htmlspecialchars($properties['title']) . '"' : '')
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
            (! empty($this->settings['filesForLayout']))
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
    public function getLayoutOfPage (): string
    {

        $request = $this->getRequest();

        /** @var \TYPO3\CMS\Core\Routing\SiteRouteResult $pageArguments */
        $pageArguments = $request->getAttribute('routing');
        if (method_exists($pageArguments, 'getPageUid')) {
            $pageId = $pageArguments->getPageId();
        } else {
            /** discouraged since TYPO3 v12 */
            $pageId = intval($GLOBALS['TSFE']->id);
        }

        // get rootline
        $rootlinePages = GeneralUtility::makeInstance(RootlineUtility::class, $pageId)->get();
        $layout = '';

        if (is_array($rootlinePages)) {
            foreach ($rootlinePages as $key => $page) {

                // own layout-field overrides all
                if (
                    ($key == (count($rootlinePages)-1))
                    && ($page['backend_layout'])
                ) {
                    return str_replace('pagets__', '', $page['backend_layout']);
                }

                // inherit layout from parent pages
                if (
                    ($key != (count($rootlinePages)-1))
                    && ($page['backend_layout_next_level'])
                ) {
                    return str_replace('pagets__', '', $page['backend_layout_next_level']);
                }

            }
        }

        return $layout;
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
            'filesForLayout' => [],
            'filesToRemoveWhenActive' => []
        ];

        if ($request) {

            // NullSite will be set if in backend context
            if (
                ($site = $request->getAttribute('site'))
                && (! $site instanceof \TYPO3\CMS\Core\Site\Entity\NullSite)
            ) {

                if (
                    ($siteConfiguration = $site->getConfiguration())
                    && (isset($siteConfiguration['accelerator']['criticalCss']))
                ){
                    $settings = array_merge($settings, $siteConfiguration['accelerator']['criticalCss'] ?? []);
                    $settings['enable'] = $this->resolveEnableWithVariants(
                        $settings['enable'],
                        $siteConfiguration['acceleratorVariants']
                    );
                }

                // deactive critical css if it is rendered via pageType
                if (
                    ($params = $request->getQueryParams())
                    && (
                        ($params['type'] == '1715339215')
                        || ($params['no_critical_css'] == '1')
                    )
                ){
                    $settings['enable'] = 0;
                }
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
                        && ($variant['criticalCss']['enable'])
                    ){
                        $enable = intval($variant['criticalCss']['enable']);
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

   /**
     * @return \Psr\Http\Message\ServerRequestInterface|null
     */
    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }

}
