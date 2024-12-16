<?php
declare(strict_types=1);
namespace Madj2k\Accelerator\Persistence\Representations;

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
 * Class ReducedCollection
 *
 * Represents a reduced collection of objects.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Christian Dilger <c.dilger@addorange.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @api
 */
class ReducedCollection extends ReducedValue
{
    /**
     * @var array<string>
     */
    private array $references;


    /**
     * ReducedCollection constructor.
     *
     * @param array<string> $references
     */
    public function __construct(array $references)
    {
        $this->references = $references;
    }


    /**
     * Gets the references of the reduced collection.
     *
     * @return array<ReducedReference> An array of ReducedReference objects.
     */
    public function getReferences(): array
    {
        return $this->references;
    }
}
