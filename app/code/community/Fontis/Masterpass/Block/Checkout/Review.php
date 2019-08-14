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

class Fontis_Masterpass_Block_Checkout_Review extends Fontis_Masterpass_Block_Abstract
{
    const ACCEPTANCE_MARK_URL = "http://www.mastercard.com/mc_us/wallet/img/en/AU/mp_acc_068px_gif.gif";

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var Mage_Sales_Model_Quote_Address
     */
    protected $_shippingAddress = null;

    /**
     * Currently selected shipping rate.
     *
     * @var Mage_Sales_Model_Quote_Address_Rate
     */
    protected $_currentShippingRate = null;

    /**
     * @return string
     */
    public function getMasterpassAcceptanceMarkUrl()
    {
        return self::ACCEPTANCE_MARK_URL;
    }

    /**
     * Quote object setter.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return Fontis_Masterpass_Block_Checkout_Review
     */
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    /**
     * Get checkout quote object.
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if ($this->_quote === null) {
            $this->_quote = Mage::getSingleton("checkout/session")->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Get billing address from quote.
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getBillingAddress()
    {
        return $this->getQuote()->getBillingAddress();
    }

    /**
     * Get shipping address from quote.
     * Returns null if the quote only contains virtual products.
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getShippingAddress()
    {
        if ($this->getQuote()->isVirtual()) {
            return null;
        }
        return $this->getQuote()->getShippingAddress();
    }

    /**
     * Get payment details from quote.
     *
     * @return Mage_Sales_Model_Quote_Payment
     */
    public function getQuotePayment()
    {
        return $this->getQuote()->getPayment();
    }

    /**
     * Get HTML output for supplied address object.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @return string
     */
    public function renderAddress(Mage_Sales_Model_Quote_Address $address)
    {
        return $address->getFormated(true);
    }

    /**
     * Return carrier name from config, base on carrier code
     *
     * @param string $carrierCode
     * @return string
     */
    public function getCarrierName($carrierCode)
    {
        if ($name = Mage::getStoreConfig("carriers/{$carrierCode}/title")) {
            return $name;
        }
        return $carrierCode;
    }

    /**
     * Get either shipping rate code or empty value on error
     *
     * @param Varien_Object $rate
     * @return string
     */
    public function renderShippingRateValue(Varien_Object $rate)
    {
        if ($rate->getErrorMessage()) {
            return '';
        }
        return $rate->getCode();
    }

    /**
     * Get shipping rate code title and its price or error message
     *
     * @param Varien_Object $rate
     * @param string $format
     * @param string $inclTaxFormat
     * @return string
     */
    public function renderShippingRateOption($rate, $format = '%s - %s%s', $inclTaxFormat = ' (%s %s)')
    {
        $renderedInclTax = '';
        if ($rate->getErrorMessage()) {
            $price = $rate->getErrorMessage();
        } else {
            $price = $this->_getShippingPrice($rate->getPrice(), $this->helper('tax')->displayShippingPriceIncludingTax());
            $incl = $this->_getShippingPrice($rate->getPrice(), true);
            if (($incl != $price) && $this->helper('tax')->displayShippingBothPrices()) {
                $renderedInclTax = sprintf($inclTaxFormat, Mage::helper('tax')->__('Incl. Tax'), $incl);
            }
        }
        return sprintf($format, $rate->getMethodTitle(), $price, $renderedInclTax);
    }

    /**
     * Getter for current shipping rate
     *
     * @return Mage_Sales_Model_Quote_Address_Rate
     */
    public function getCurrentShippingRate()
    {
        return $this->_currentShippingRate;
    }

    /**
     * Return formatted shipping price.
     *
     * @param float $price
     * @param bool $isInclTax
     *
     * @return bool
     */
    protected function _getShippingPrice($price, $isInclTax)
    {
        return $this->_formatPrice($this->helper('tax')->getShippingPrice($price, $isInclTax, $this->_shippingAddress));
    }

    /**
     * Format price based on store convert price method.
     *
     * @param float $price
     * @return string
     */
    protected function _formatPrice($price)
    {
        return $this->getQuote()->getStore()->convertPrice($price, true);
    }

    /**
     * Retrieve payment method and assign additional template values
     *
     * @return Fontis_Masterpass_Block_Checkout_Review
     */
    protected function _beforeToHtml()
    {
        $this->setPaymentMethodTitle($this->getQuotePayment()->getMethodInstance()->getTitle());

        $quote = $this->getQuote();
        if ($quote->getIsVirtual()) {
            $this->setShippingRateRequired(false);
        } else {
            $this->setShippingRateRequired(true);

            // prepare shipping rates
            $this->_shippingAddress = $quote->getShippingAddress();
            $groups = $this->_shippingAddress->getGroupedAllShippingRates();
            if ($groups && $this->_shippingAddress) {
                $this->setShippingRateGroups($groups);
                // determine current selected code & name
                foreach ($groups as $code => $rates) {
                    foreach ($rates as $rate) {
                        if ($this->_shippingAddress->getShippingMethod() == $rate->getCode()) {
                            $this->_currentShippingRate = $rate;
                            break(2);
                        }
                    }
                }
            }

            // misc shipping parameters
            $this->setShippingMethodSubmitUrl($this->getUrl("masterpass/checkout/saveShippingMethod"))
                ->setCanEditShippingAddress(false) // MasterPass doesn't let the customer return to edit details at present.
                ->setCanEditShippingMethod(true); // MasterPass doesn't support handling shipping methods at present, so this must always be true.
        }

        $this->setPlaceOrderUrl($this->getUrl("masterpass/checkout/placeOrder"));

        return parent::_beforeToHtml();
    }
}
