<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Persistence;

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

use Madj2k\CoreExtended\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;


/**
 * A class to reduce the size of objects in order to be able to persist them in a database.
 *
 * This class is used to transform objects and object collections into simpler references that can be stored
 * more efficiently in a database. It handles both persisted and non-persisted objects, reducing only the
 * persisted objects.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Christian Dilger <c.dilger@addorange.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @api
 */
final class MarkerReducer implements MarkerReducerInterface
{
    /**
     * Reduces the size of objects in an array by replacing persisted objects with references.
     * Also processes non-persisted objects by checking their properties for persisted objects.
     *
     * @param array $marker The array containing objects to be reduced.
     * @return array<string, mixed> An associative array with strings as keys.
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function implode(array $marker): array
    {
        $configReader = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $extensionConfig = $configReader->get('accelerator');

        if ($extensionConfig['markerReducerVersion'] == 'advanced') {
            return MarkerReducerAdvanced::implode($marker);
        }

        return MarkerReducer::implode($marker);
    }


    /**
     * Rebuilds reduced objects from their references or ReducedObject instances in the marker array.
     *
     * @param array $marker The array containing reduced references or ReducedObject instances.
     * @return array<string, mixed> An associative array with rebuilt objects or the original values if no reduction occurred.
     * @throws \ReflectionException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public static function explode(array $marker): array
    {
        $configReader = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $extensionConfig = $configReader->get('accelerator');

        if ($extensionConfig['markerReducerVersion'] == 'advanced') {
            return MarkerReducerAdvanced::explode($marker);
        }

        return MarkerReducer::explode($marker);
    }

}
