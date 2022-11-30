<?php
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

use TYPO3\CMS\Core\Resource\ResourceCompressor;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Class CriticalCss
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class CriticalCss
{

    /**
     * @var array
     */
    protected $settings;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->settings = $this->getSettings();
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


        // first of all we rebuild the existing cssIncludes
        /** @see: PageRenderer->renderCssFiles() */
        $cssCode = [];
        if (is_array($params['cssFiles'])) {
            foreach ($params['cssFiles'] as $file => $properties) {

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

                if ($properties['forceOnTop']) {
                    array_unshift($cssCode, $tag);
                } else {
                    $cssCode[] = $tag;
                }

                // remove the file from further processing
                unset($params['cssFiles'][$file]);
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
            && (isset($this->settings['filesForLayout'][$this->getFrontendLayoutOfPage()]))
            && (is_array($this->settings['filesForLayout'][$this->getFrontendLayoutOfPage()]))
        ){
            return $this->settings['filesForLayout'][$this->getFrontendLayoutOfPage()];
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
     * @return int
     */
    public function getFrontendLayoutOfPage (): int
    {

        // get PageRepository and rootline
        $rootlinePages = GeneralUtility::makeInstance(RootlineUtility::class, intval($GLOBALS['TSFE']->id))->get();
        $layout = 0;
        if (is_array($rootlinePages)) {
            foreach ($rootlinePages as $key => $page) {

                // own layout-field overrides all
                if (
                    ($key == (count($rootlinePages)-1))
                    && ($page['layout'])
                ) {
                    return intval($page['layout']);
                }

                // inherit layout from parent pages
                if (
                    ($key != (count($rootlinePages)-1))
                    && ($page['tx_root_fe_layout_next_level'])
                ) {
                    return intval($page['tx_root_fe_layout_next_level']);
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
                array_push(  $mediaArray, 'print');
            }
            if ($mediaString == 'all') {
                unset($mediaArray[$key]);
                array_push(  $mediaArray, 'print', 'speech');
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
     * @return array
     */
    public function getSettings(): array
    {

        $settings = [
            'enable' => 0,
            'filesForLayout' => [],
            'filesToRemoveWhenActive' => []
        ];

        /** @var  $objectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configurationManager = $objectManager->get(ConfigurationManagerInterface::class);
        $configuration = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Accelerator'
        );

        if (
            (isset($configuration['criticalCss']))
            && (is_array($configuration['criticalCss']))
        ){
            $settings = array_merge($settings, $configuration['criticalCss']);
        }

        return $settings;
    }

}
