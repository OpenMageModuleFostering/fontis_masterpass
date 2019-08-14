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

class Fontis_Masterpass_Model_System_Cards extends Mage_Core_Model_Config_Data
{
    const MASTERCARD    = "master";
    const AMEX          = "amex";
    const DINERS        = "diners";
    const DISCOVER      = "discover";
    const MAESTRO       = "maestro";
    const VISA          = "visa";

    public static $_options = array(
        self::MASTERCARD    => "MasterCard",
        self::AMEX          => "American Express",
        self::DINERS        => "Diners Club",
        self::DISCOVER      => "Discover",
        self::MAESTRO       => "Maestro",
        self::VISA          => "Visa",
    );

    const MAGE_MASTERCARD   = "MC";
    const MAGE_AMEX         = "AE";
    const MAGE_DINERS       = "DICL";
    const MAGE_DISCOVER     = "DI";
    const MAGE_MAESTRO      = "SM";
    const MAGE_VISA         = "VI";

    public static $_mageMap = array(
        self::MASTERCARD    => self::MAGE_MASTERCARD,
        self::AMEX          => self::MAGE_AMEX,
        self::DINERS        => self::MAGE_DINERS,
        self::DISCOVER      => self::MAGE_DISCOVER,
        self::MAESTRO       => self::MAGE_MAESTRO,
        self::VISA          => self::MAGE_VISA,
    );

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        foreach (self::$_options as $key => $option) {
            $options[] = array("value" => $key, "label" => $option);
        }
        return $options;
    }

    /**
     * @return array
     */
    public function getCards()
    {
        return self::$_options;
    }

    /**
     * @param string $masterpassCode
     * @return string|null
     */
    public function lookupMageCode($masterpassCode)
    {
        if (!array_key_exists($masterpassCode, self::$_mageMap)) {
            return null;
        } else {
            return self::$_mageMap[$masterpassCode];
        }
    }

    /**
     * Ensure the selected credit card types are available for the selected payment gateway.
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        if ($config = $this->_pullOutConfig()) {
            $paymentConfig = Mage::getStoreConfig("payment");
            $validCCTypes = explode(",", Mage::getModel($paymentConfig[$config["value"]]["model"])->getConfigData("cctypes"));

            foreach ($config["cards"] as $card) {
                $mageCode = $this->lookupMageCode($card);
                if (!in_array($mageCode, $validCCTypes)) {
                    Mage::throwException("One or more of the accepted cards you selected is not enabled for use with the payment gateway you selected.");
                }
            }
        }

        return parent::_beforeSave();
    }

    /**
     * This function is designed to be called when a user clicks on the Save button on the
     * MasterPass system configuration page. It checks for the relevant POST data, and if
     * it is there, returns it.
     *
     * @return array|null
     */
    protected function _pullOutConfig()
    {
        if ($result = Mage::app()->getRequest()->getPost("groups")) {
            if (isset($result["settings"]) && $result = $result["settings"]) {
                if (isset($result["fields"]) && $fields = $result["fields"]) {
                    if (isset($fields["payment_gateway"]) && $result = $fields["payment_gateway"]) {
                        if (isset($result["value"]) && $value = $result["value"]) {
                            if (isset($fields["accepted_cards"]) && $result = $fields["accepted_cards"]) {
                                if (isset($result["value"]) && $cards = $result["value"]) {
                                    return array("value" => $value, "cards" => $cards);
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }
}
