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

class Fontis_Masterpass_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Value specified in seconds.
     */
    const REDUCED_SESSION_LIFETIME = 300;

    const MPASS_CC_NUMBER_ENC = "masterpassCcNumberEnc";
    const MPASS_TXNID_LABEL = "mpass_txnid";

    // API URLs
    const URL_API_OAUTH    = 1;
    const URL_API_ONLINE   = 2;

    /**
     * @var array
     */
    protected $_baseUrls = array(
        Fontis_Masterpass_Model_System_Environment::ENV_PROD => "https://api.mastercard.com/",
        Fontis_Masterpass_Model_System_Environment::ENV_SAND => "https://sandbox.api.mastercard.com/",
    );

    /**
     * @var array
     */
    protected $_apiUrls = array(
        self::URL_API_OAUTH     => "oauth/",
        self::URL_API_ONLINE    => "online/",
    );

    /**
     * @var array
     */
    protected $_sensitiveRequests = array(
        "continue",
        "review",
        "saveShippingMethod",
        "placeOrder",
    );

    /**
     * This is THE function to call to check whether or not MasterPass is to be used.
     * It checks several things to make sure that a transaction can actually happen
     * with MasterPass, as well as checking the "enabled" config setting.
     * This function is called at the start of every controller action, and should
     * be called anywhere that is necessary to see whether the extension is active.
     *
     * @return bool
     */
    public function isEnabled()
    {
        if (Mage::getStoreConfig("fontis_masterpass/settings/enabled")) {
            /** @var Fontis_Masterpass_Helper_Config $configHelper */
            $configHelper = Mage::helper("fontis_masterpass/config");
            if (!$configHelper->getCheckoutIdentifier()) {
                return false;
            }
            if (!$configHelper->getConsumerKey()) {
                return false;
            }
            if (!$configHelper->getPrivateKey()) {
                return false;
            }
            if (!($paymentGateway = $configHelper->getPaymentGateway())) {
                return false;
            }
            if ($configHelper->getAcceptedCards(false) === null) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function canShowOnCheckout()
    {
        if ($this->isEnabled()) {
            return Mage::getStoreConfigFlag("fontis_masterpass/settings/show_on_checkout");
        }
        return false;
    }

    /**
     * Temporarily disable the CCV check on the payment gateway selected from the
     * MasterPass system configuration page. This will allow MasterPass to process
     * payments without having to turn off the "Use CCV" setting on the gateway.
     */
    public function temporarilyDisableCCVCheck()
    {
        $paymentGateway = Mage::helper("fontis_masterpass/config")->getPaymentGateway();

        if (empty($paymentGateway)) {
            Mage::throwException("Unable to place the order.");
        }

        Mage::app()->getStore()->setConfig("payment/$paymentGateway/useccv", "0");
    }

    /**
     * This should cause a client's session lifetime to be reduced to just five
     * minutes. We do this when storing a customer's credit card details in their
     * session, to ensure the data does not persist for very long once they leave
     * the site.
     */
    public function reduceClientSessionLifetime()
    {
        Mage::app()->getStore()->setConfig("web/cookie/cookie_lifetime", self::REDUCED_SESSION_LIFETIME);
    }

    /**
     * Returns the URL that commences the MasterPass checkout process.
     * It should point to the "start" controller action in the Checkout Controller inside this MasterPass module.
     *
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return Mage::getUrl("masterpass/checkout/start");
    }

    /**
     * Returns the URL that customers should be sent back to from MasterPass (regardless of success or fail).
     * It should point to the "continue" controller action in the Checkout Controller inside this MasterPass module.
     *
     * @return string
     */
    public function getCallbackUrl()
    {
        return Mage::getUrl("masterpass/checkout/continue");
    }

    /**
     * @param int $environment
     * @return string|null
     */
    public function getMasterpassBaseUrl($environment)
    {
        if (isset($this->_baseUrls[$environment])) {
            return $this->_baseUrls[$environment];
        } else {
            return null;
        }
    }

    /**
     * @param int $api
     * @param string $route
     * @return string|null
     */
    public function getMasterpassApiUrl($api, $route)
    {
        if (isset($this->_apiUrls[$api])) {
            $url = $this->getMasterpassBaseUrl(Mage::helper("fontis_masterpass/config")->getCurrentEnvironment());
            $url .= $this->_apiUrls[$api] . $route;
            return $url;
        } else {
            return null;
        }
    }

    /**
     * @param string $route
     * @return string|null
     */
    public function getMasterpassOauthUrl($route)
    {
        return $this->getMasterpassApiUrl(self::URL_API_OAUTH, $route);
    }

    /**
     * @param string $route
     * @return string|null
     */
    public function getMasterpassOnlineUrl($route)
    {
        return $this->getMasterpassApiUrl(self::URL_API_ONLINE, $route);
    }

    /**
     * Encodes all ASCII character to their decimal encodings.
     *
     * @param string $str
     * @return string
     */
    public function htmlEncode($str)
    {
        // get rid of existing entities else double-escape
        $str = html_entity_decode(stripslashes($str), ENT_QUOTES, "UTF-8");
        $ar = preg_split('/(?<!^)(?!$)/u', $str );  // return array of every multi-byte character
        $str2 = "";
        foreach ($ar as $c) {
            $o = ord($c);
            if ((strlen($c) > 127) || /* multi-byte [unicode] */
                ($o > 127))           /*Encodes everything above ascii 127*/
            {
                // convert to numeric entity
                $c = mb_encode_numericentity($c, array(0x0, 0xffff, 0, 0xffff), "UTF-8");
            }
            $str2 .= $c;
        }
        return $str2;
    }

    /**
     * Format a date in the format MasterPass expects.
     * The function should take in a timestamp in the Unix date format.
     * If the supplied date is not in this format, it attempts to convert it to this format.
     *
     * @param string $date
     * @return string
     */
    public function formatDate($date)
    {
        if (!is_numeric($date)) {
            $date = strtotime($date);
        }
        return date('Y-m-d\TH:i:s.000P', $date);
    }

    /**
     * Check to see if an action is a "sensitive" action. This means that an encrypted credit card number
     * is in the customer's session.
     *
     * @param Mage_Core_Controller_Request_Http $request
     * @return bool
     */
    public function checkIfSensitiveRequest(Mage_Core_Controller_Request_Http $request)
    {
        if ($request->getRequestedRouteName() == "masterpass" && in_array($request->getRequestedActionName(), $this->_sensitiveRequests)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check to see whether or not the current page is using SSL.
     * This affects linked content such as images and scripts.
     *
     * @return bool
     */
    public function isSecure()
    {
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") {
            return true;
        } else {
            return false;
        }
    }
}
