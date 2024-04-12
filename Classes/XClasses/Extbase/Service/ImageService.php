<?php
namespace Madj2k\Accelerator\XClasses\Extbase\Service;

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

use TYPO3\CMS\Core\Resource\ProcessedFile;


/**
 * This class enforces all images to be processed - no matter what
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @deprecated will be removed soon, deprecated since 2024-04-05
 */
class ImageService extends \TYPO3\CMS\Extbase\Service\ImageService {

    /**
     * Does exactly the same as the class it extends EXCEPT it ALWAYS creates a file
     *
     * @param \TYPO3\CMS\Core\Resource\FileInterface $image
     * @param array $processingInstructions
     * @return \TYPO3\CMS\Core\Resource\ProcessedFile
     * @api
     */
    public function applyProcessingInstructions($image, $processingInstructions): ProcessedFile
    {
        $processingInstructions['additionalParameters'] = '-rotate 360';
        return parent::applyProcessingInstructions($image, $processingInstructions);
    }
}
