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

class Fontis_Masterpass_Model_System_Environment
{
    const ENV_PROD = 1;
    const ENV_SAND = 2;

    public static $_options = array(
        self::ENV_PROD  => "Production",
        self::ENV_SAND  => "Sandbox",
    );

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $helper = Mage::helper("fontis_masterpass");
        return array(
            array("value" => self::ENV_PROD, "label" => $helper->__(self::$_options[self::ENV_PROD])),
            array("value" => self::ENV_SAND, "label" => $helper->__(self::$_options[self::ENV_SAND])),
        );
    }
}
