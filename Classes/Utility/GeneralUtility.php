<?php
namespace Madj2k\Accelerator\Utility;

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
 * Class GeneralUtility
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @deprecated deprecated since v13.4.0. Will be removed soon.
 */
class GeneralUtility extends \TYPO3\CMS\Core\Utility\GeneralUtility
{


    /**
     * @param string $name
     * @return string
     */
    public static function underscore(string $name): string
    {
        return strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $name));
    }



    /**
     * Converts field names for setters and getters
     * Uses cache to eliminate unnecessary preg_replace
     *
     * @param string $name
     * @param string $destSep
     * @param string $srcSep
     * @return string
     */
    public static function camelize(string $name, string $destSep = '', string $srcSep = '_'): string
    {
        return lcfirst(str_replace(' ', $destSep, ucwords(str_replace($srcSep, ' ', $name))));
    }

}
