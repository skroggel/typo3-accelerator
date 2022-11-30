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

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;

/**
 * This class enforces all images to be processed - no matter what
 */
class ImageService extends \TYPO3\CMS\Extbase\Service\ImageService {

    /**
     * Does exactly the same as the class it extends EXCEPT it ALWAYS creates a file
     *
     * @param File|FileReference $image
     * @param array $processingInstructions
     * @return ProcessedFile
     * @api
     */
    public function applyProcessingInstructions($image, $processingInstructions)
    {
        $processingInstructions['additionalParameters'] = '-rotate 360';
        return parent::applyProcessingInstructions($image, $processingInstructions);
    }
}
