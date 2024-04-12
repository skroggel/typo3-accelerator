<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Hooks;

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

use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * Class InlineCssHook
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @deprecated will be removed soon, deprecated since 2024-04-05
 */
final class InlineCssHook
{

    /**
     * Takes processed JS and CSS files and transfers them to inline versions
     *
     * @param array $params
     * @param \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer
     * @return void The content is passed by reference
     */
    public function render_postTransform(array &$params, PageRenderer $pageRenderer): void
    {

        foreach (['cssFiles', 'cssLibs'] as $category) {

            $inlineCss = [];
            foreach ($params[$category] as $cssFile => $setup) {
                $cssFilePath = $cssFile;
                if (
                    (strpos($cssFile, 'http://') === false)
                    && (strpos($cssFile, 'https://') === false)
                    && (strpos($cssFile, '//') === false)
                ) {
                    $cssFilePath = \TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName($cssFilePath);
                }

                if ($content = file_get_contents($cssFilePath)) {

                    $media = $setup['media'] ? $setup['media'] : '';
                    $code = '<style type="text/css" media="' . $media . '">' .
                        LF . '/*<![CDATA[*/' .
                        LF . '<!-- ' .
                        LF . $content .
                        LF . '-->' .
                        LF . '/*]]>*/' .
                        LF . '</style>';

                    if ($setup['forceOnTop']) {
                        array_unshift($inlineCss, $code);
                    } else {
                        $inlineCss[] = $code;
                    }
                    unset($params['cssFiles'][$cssFile]);
                }
            }
            array_unshift($params['headerData'], implode(LF, $inlineCss));
        }
    }
}
