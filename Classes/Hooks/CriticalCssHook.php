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

use Madj2k\Accelerator\ContentProcessing\CriticalCss;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class CriticalCssHook
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
final class CriticalCssHook
{

    /**
     * Called before page is outputed
     *
     * @param array $params
     * @param \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer
     * @return void The content is passed by reference
     */
    function render_postTransform(array &$params, \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer): void
    {

        /** @var \Madj2k\Accelerator\ContentProcessing\CriticalCss $criticalCss */
        $criticalCss = GeneralUtility::makeInstance(CriticalCss::class);

        // Replace
        array_unshift($params['headerData'], $criticalCss->process($params, $pageRenderer));
    }


    /**
     * Called before page is rendered
     *
     * @param array $params
     * @param \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer
     * @return void The content is passed by reference
     */
    function render_preProcess(array &$params, \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer): void
    {

        /** @var \Madj2k\Accelerator\ContentProcessing\CriticalCss $criticalCss */
        $criticalCss = GeneralUtility::makeInstance(CriticalCss::class);
        $criticalCss->preProcess($params, $pageRenderer);
    }

}
