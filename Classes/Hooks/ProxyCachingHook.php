<?php

namespace Madj2k\Accelerator\Hooks;

use Madj2k\Accelerator\ContentProcessing\ProxyCaching;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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

/**
 * Class ProxyCachingHook
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ProxyCachingHook
{

    /**
     * ContentPostProc-output hook to add some additional headers
     *
     * @param array $parameters Parameter
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $parent The parent object
     * @return void
     */
    public function sendHeader(array $parameters, TypoScriptFrontendController $parent)
    {

        /** @var $proxyCaching \Madj2k\Accelerator\ContentProcessing\ProxyCaching */
        $proxyCaching = GeneralUtility::makeInstance(ProxyCaching::class);
        $pid = intval($GLOBALS['TSFE']->id);

        header('X-TYPO3-ProxyCaching: ' . $proxyCaching::getProxyCachingSetting($pid));
        header('xkey: ' . $proxyCaching::getSiteTag() . ' ' . $proxyCaching::getSiteTag() . '_' . $pid);
    }

}
