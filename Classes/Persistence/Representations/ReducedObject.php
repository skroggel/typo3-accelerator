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
 * Class ReducedObject
 *
 * Represents a reduced object.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Christian Dilger <c.dilger@addorange.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @api
 */
class ReducedObject extends ReducedValue
{
    /**
     * @var string
     */
    private string $key;


    /**
     * @var array<string, mixed>
     */
    private array $properties;


    /**
     * ReducedObject constructor.
     *
     * @param string $key
     * @param array<string, mixed> $properties
     */
    public function __construct(string $key, array $properties)
    {
        $this->key = $key;
        $this->properties = $properties;
    }


    /**
     * Gets the key of the reduced object.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }


    /**
     * Gets the properties of the reduced object.
     *
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
