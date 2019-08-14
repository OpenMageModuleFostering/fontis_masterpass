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

class Fontis_Masterpass_Helper_Config extends Mage_Core_Helper_Abstract
{
    /**
     * This string should be used whereever the "MasterPass" branding name should appear
     * on the frontend in text form.
     *
     * @return string
     */
    public function getFrontendTitle()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/title");
    }

    /**
     * @return string
     */
    public function getCheckoutButtonUrl()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/checkoutbutton_url");
    }

    /**
     * @return int
     */
    public function getCheckoutButtonSize()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/checkoutbutton_size");
    }

    /**
     * @return string
     */
    public function getAcceptanceMarkUrl()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/acceptancemark_url");
    }

    /**
     * @return int
     */
    public function getAcceptanceMarkSize()
    {
        return (int) Mage::getStoreConfig("fontis_masterpass/settings/acceptancemark_size");
    }

    /**
     * @return string
     */
    public function getLearnMoreLanguage()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/learn_more_language");
    }

    /**
     * @return int
     */
    public function getCurrentEnvironment()
    {
        return (int) Mage::getStoreConfig("fontis_masterpass/settings/environment");
    }

    /**
     * The Checkout Identifier tells MasterPass which "checkout" to display to the user.
     * This allows MasterPass to display some branded content (eg logos) to give the impression it is associated
     * with the organisation using MasterPass.
     *
     * @return string
     */
    public function getCheckoutIdentifier()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/checkout_identifier");
    }

    /**
     * @return string
     */
    public function getConsumerKey()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/consumer_key");
    }

    /**
     * Get the code of the payment gateway used by the merchant to process credit card payments.
     *
     * @return string
     */
    public function getPaymentGateway()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/payment_gateway");
    }

    /**
     * @return int
     */
    public function getPaymentSortOrder()
    {
        return (int) Mage::getStoreConfig("fontis_masterpass/settings/sort_order");
    }

    public function isTokenPaymentsAccepted()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/token_payments_accepted");
    }

    /**
     * @param bool $returnAsArray
     * @return string|array
     */
    public function getAcceptedCards($returnAsArray = false)
    {
        $cards = Mage::getStoreConfig("fontis_masterpass/settings/accepted_cards");
        if ($cards === null) {
            return null;
        }
        if ($returnAsArray) {
            $cards = explode(",", $cards);
        }
        return $cards;
    }

    /**
     * @return string
     */
    public function getPrivateKey()
    {
        return Mage::getStoreConfig("fontis_masterpass/settings/private_key");
    }
}
