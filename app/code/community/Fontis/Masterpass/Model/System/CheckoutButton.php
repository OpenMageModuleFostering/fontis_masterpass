<?php
/**
 * Fontis MasterPass Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Fontis
 * @package    Fontis_MasterPass
 * @author     Matthew Gamble
 * @copyright  Copyright (c) 2014 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fontis_Masterpass_Model_System_CheckoutButton
{
    const SIZE_SMALL    = 1;
    const SIZE_MEDIUM   = 2;
    const SIZE_LARGE    = 3;
    const SIZE_LARGEST  = 4;

    protected $sizes = array(
        self::SIZE_SMALL    => "Small",
        self::SIZE_MEDIUM   => "Medium",
        self::SIZE_LARGE    => "Large",
        self::SIZE_LARGEST  => "Largest",
    );

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        $helper = Mage::helper("fontis_masterpass");
        foreach ($this->sizes as $size => $label) {
            $options[] = array(
                "label" => $helper->__($label),
                "value" => $size,
            );
        }
        return $options;
    }
}
