<?php

namespace Madj2k\Accelerator\Domain\Model;

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
 * Class Pages
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Pages extends \Madj2k\CoreExtended\Domain\Model\Pages
{


    /**
     * @var integer
     */
    protected $txAcceleratorProxyCaching = 0;


    /**
     * Returns the txAcceleratorProxyCaching
     *
     * @return int
     */
    public function getTxAcceleratorProxyCaching(): int
    {
        return $this->pid;
    }


    /**
     * Sets the txAcceleratorProxyCaching
     *
     * @param int $txAcceleratorProxyCaching
     * @return void
     * @api
     */
    public function setTxAcceleratorProxyCaching(int $txAcceleratorProxyCaching): void
    {
        $this->txAcceleratorProxyCaching = $txAcceleratorProxyCaching;
    }

}
