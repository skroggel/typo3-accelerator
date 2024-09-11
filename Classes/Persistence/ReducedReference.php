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

/**
 * Class ReducedReference
 *
 * Represents a single reduced reference.
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @author Christian Dilger <c.dilger@addorange.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @api
 */
class ReducedReference extends ReducedValue
{
    /**
     * @var string
     */
    private string $namespace;

    /**
     * @var int
     */
    private int $uid;

    /**
     * ReducedReference constructor.
     *
     * @param string $namespace
     * @param int $uid
     */
    public function __construct(string $namespace, int $uid)
    {
        $this->namespace = $namespace;
        $this->uid = $uid;
    }

    /**
     * Gets the namespace of the reduced reference.
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Gets the UID of the reduced reference.
     *
     * @return int
     */
    public function getUid(): int
    {
        return $this->uid;
    }

    /**
     * Converts the ReducedReference to a string in the format "namespace:uid".
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->namespace . ':' . $this->uid;
    }
}
