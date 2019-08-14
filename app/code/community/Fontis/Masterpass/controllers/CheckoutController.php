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

class Fontis_Masterpass_CheckoutController extends Mage_Core_Controller_Front_Action
{
    const RETURN_SUCCESS = 200;
    const MPASS_REQUEST_TOKEN = "masterpassRequestToken";
    const MPASS_TRANSACTION_ID = "masterpassTransactionId";
    const MPASS_CARTXML_OAUTHTOKEN = "OAuthToken";
    const MPASS_ORDERXML_CONSUMERKEY = "ConsumerKey";
    const LOGFILE = "fontis_masterpass.log";

    /**
     * Stores the customer's quote object.
     *
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * Stores the customer's checkout session object.
     *
     * @var Mage_Checkout_Model_Session
     */
    protected $_checkoutSession = null;

    /**
     * @var Fontis_Masterpass_Model_Masterpass_Oauth
     */
    protected $_oauth = null;

    /**
     * @var Fontis_Masterpass_Model_Checkout
     */
    protected $_checkout = null;

    public function preDispatch()
    {
        $actionName = $this->getActionMethodName($this->getRequest()->getRequestedActionName());
        if (!is_callable(array($this, $actionName))) {
            // This request is going to 404, so we don't care.
            return parent::preDispatch();
        }
        if (!Mage::helper('fontis_masterpass/config')->isTokenPaymentsAccepted()) {
            if (Mage::helper("fontis_masterpass")->checkIfSensitiveRequest($this->getRequest())) {
                Mage::helper("fontis_masterpass")->reduceClientSessionLifetime();
            }
        }
        return parent::preDispatch();
    }

    /**
     * Starts the MasterPass checkout process.
     * Activated when the user clicks the "Checkout with MasterPass" button on the cart page.
     *
     * @return $this
     */
    public function startAction()
    {
        if (!Mage::helper("fontis_masterpass")->isEnabled()) {
            // If MasterPass isn't enabled, pretend this controller action doesn't exist.
            $this->_forward("noroute");
            return $this;
        }

        try {
            $this->_initialize();

            // Start the connection to MasterPass and receive an OAuth token.
            if (!($requestToken = $this->_initiateMasterpassConnection())) {
                $this->_getCheckoutSession()->addError($this->__("Sorry, we were unable to start the checkout process with %s. Please try again.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
                return $this->_redirect("checkout/cart");
            }

            // Create an Order ID for the customer's quote. Used to track their order across requests.
            $this->_getQuote()->reserveOrderId()->save();

            // Submit the customer's shopping cart to MasterPass in XML form.
            if ($this->_submitShoppingCartXml($requestToken)) {
                // If we successfully submitted the customer's shopping cart to MasterPass, redirect the user to MasterPass.
                return $this->_redirectToMasterpass($requestToken);
            } else {
                $this->_getCheckoutSession()->addError($this->__("Sorry, we were unable to start the checkout process with %s. Please try again.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
            }
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($this->__("Sorry, we were unable to start the checkout process with %s. Please try again.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
        }

        // If we reach here, there was an error of some kind.
        return $this->_redirect("checkout/cart");
    }

    /**
     * The MasterPass callback controller action.
     * This is where MasterPass will redirect the user back to regardless of how they exit the checkout, so it needs
     * to handle success and failure.
     *
     * @return $this
     */
    public function continueAction()
    {
        if (!Mage::helper("fontis_masterpass")->isEnabled()) {
            // If MasterPass isn't enabled, pretend this controller action doesn't exist.
            $this->_forward("noroute");
            return $this;
        }

        try {
            $this->_initialize();

            // Check that we received all of the necessary information from MasterPass during the redirect to enable
            // us to request the customer's payment details.
            if (!$this->_verifyCallback()) {
                return $this->_redirect("checkout/cart");
            }

            // Get the customer's payment details.
            if ($paymentData = $this->_getMasterpassPaymentData()) {
                // Ensure CCV check is disabled before any payment data is imported
                Mage::helper("fontis_masterpass")->temporarilyDisableCCVCheck();

                // Attach the customer's payment details (including addresses) to their quote.
                $this->_attachMasterpassPaymentDataToQuote($paymentData);
                return $this->_redirect("*/*/review");
            } else {
                // This means we were unable to retrieve the customer's payment details.
                $this->_getCheckoutSession()->addError($this->__("Sorry, we were unable to retrieve your payment details from %s. Please try again.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
            }
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($this->__("Sorry, we didn't receive enough information back from %s to complete the checkout. Please try again.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
        }

        // If we reach here, there was an error of some kind.
        return $this->_redirect("checkout/cart");
    }

    /**
     * The MasterPass review step.
     * The customer arrives here if they successfully completed the MasterPass checkout and were successfully
     * returned to the site by MasterPass.
     * This is the final review step before confirming the order.
     *
     * @return $this
     */
    public function reviewAction()
    {
        if (!Mage::helper("fontis_masterpass")->isEnabled()) {
            // If MasterPass isn't enabled, pretend this controller action doesn't exist.
            $this->_clearCcNumber();
            $this->_forward("noroute");
            return $this;
        }

        try {
            $this->_initialize();

            // Ensure CCV check is disabled before any validation of payment details
            Mage::helper("fontis_masterpass")->temporarilyDisableCCVCheck();

            $this->_getQuote()->setMayEditShippingMethod(true)->save();

            $this->loadLayout();
            $this->_initLayoutMessages("checkout/session");
            $this->renderLayout();
            return $this;
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($this->__("Sorry, there was a problem loading your details. Please try again."));
        }

        // If we reach here, there was an error of some kind.
        return $this->_redirect("checkout/cart");
    }

    /**
     * Update shipping method on quote.
     * This controller action supports ajax and non-ajax requests.
     *
     * @return $this
     */
    public function saveShippingMethodAction()
    {
        if (!Mage::helper("fontis_masterpass")->isEnabled()) {
            // If MasterPass isn't enabled, pretend this controller action doesn't exist.
            $this->_clearCcNumber();
            $this->_forward("noroute");
            return $this;
        }

        $isAjax = $this->getRequest()->getParam("isAjax");

        try {
            $this->_initialize();

            // Ensure CCV check is disabled before any validation of payment details
            Mage::helper("fontis_masterpass")->temporarilyDisableCCVCheck();

            $this->_checkout->updateShippingMethod($this->_getQuote(), $this->getRequest()->getParam("shipping_method"));
            if ($isAjax) {
                $this->loadLayout("masterpass_checkout_review_details");
                $response = $this->getLayout()
                    ->getBlock("root")
                    ->setQuote($this->_getQuote())
                    ->toHtml();
                $this->getResponse()->setBody($response);
                return $this;
            }
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($this->__("Unable to update shipping method."));
        }
        if ($isAjax) {
            // If we reach here, there was an error of some kind.
            $this->getResponse()->setBody('<script type="text/javascript">window.location.href = ' . Mage::getUrl("*/*/review") . ';</script>');
        } else {
            $this->_redirect("*/*/review");
        }
        return $this;
    }

    /**
     * Submit the order.
     *
     * @return $this
     */
    public function placeOrderAction()
    {
        if (!Mage::helper("fontis_masterpass")->isEnabled()) {
            // If MasterPass isn't enabled, pretend this controller action doesn't exist.
            $this->_clearCcNumber();
            $this->_forward("noroute");
            return $this;
        }

        try {
            $this->_initialize();

            // Ensure CCV check is disabled and proceed to place order
            Mage::helper("fontis_masterpass")->temporarilyDisableCCVCheck();
            $quote = $this->_getQuote();
            // Recover the customer's credit card number from their session.
            if ($ccNumber = $this->_getCcNumber()) {
                $quote->getPayment()->setCcNumber($ccNumber);
            }
            $order = $this->_checkout->placeOrder($quote);
            // We don't need this anymore. Clear it immediately.
            $this->_clearCcNumber();

            // prepare session to success or cancellation page
            $session = $this->_getCheckoutSession();
            $session->clearHelperData();

            // "last successful quote"
            $quoteId = $quote->getId();
            $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            if ($order) {
                $session->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId());
                $this->_noteSuccessfulMasterpassOrder($order);
                $result = $this->_postCheckoutSuccessBackToMasterpass($order);
                if ($result !== true) {
                    $this->_noteMasterpassPostbackFailure($order);
                }
            }

            $redirect = "checkout/onepage/success";
        } catch (Mage_Core_Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($e->getMessage());
            $redirect = "*/*/review";
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($this->__("Unable to place the order."));
            $redirect = "*/*/review";
        }

        $this->_getQuote()->save();
        return $this->_redirect($redirect);
    }

    /**
     * Instantiate quote, checkout and Masterpass Oauth objects.
     * Ensures the customer actually has items in their cart, and that they are actually worth more than zero.
     *
     * @throws Mage_Core_Exception
     */
    protected function _initialize()
    {
        $quote = $this->_getQuote();
        if (!$quote->hasItems()) {
            $this->getResponse()->setHeader("HTTP/1.1", "403 Forbidden");
            Mage::throwException($this->__("You don't have any items in your cart."));
        } elseif ($quote->getHasError()) {
            $this->getResponse()->setHeader("HTTP/1.1", "403 Forbidden");
            Mage::throwException($this->__("Sorry, we were unable to start the checkout process with %s.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
        } elseif (!$quote->getGrandTotal() && !$quote->hasNominalItems()) {
            Mage::throwException($this->__("%s does not support processing orders with zero amount. To complete your purchase, proceed to the standard checkout process.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
        }

        $this->_oauth = Mage::getModel("fontis_masterpass/masterpass_oauth");
        $this->_checkout = Mage::getModel("fontis_masterpass/checkout");
    }

    /**
     * Get checkout session object.
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        if ($this->_checkoutSession === null) {
            $this->_checkoutSession = Mage::getSingleton("checkout/session");
        }
        return $this->_checkoutSession;
    }

    /**
     * Get checkout quote object.
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        if ($this->_quote === null) {
            $this->_quote = $this->_getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Initiate the connection with MasterPass in order to get the URL to redirect the user to.
     * This involves getting a request token back from MasterPass that contains an OAuth token
     * and a URL to redirect the user to.
     *
     * @return Zend_Oauth_Token_Request|null
     */
    protected function _initiateMasterpassConnection()
    {
        $requestToken = $this->_oauth->getRequestToken();
        if (!$requestToken || $requestToken->getResponse()->getStatus() != self::RETURN_SUCCESS) {
            Mage::log("Unable to get a valid OAuth request token from MasterPass.", Zend_Log::WARN, self::LOGFILE);
            return null;
        } elseif (!($requestToken->getParam(Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_CALLBACK_CONFIRMED) == "true") ||
            !($requestToken->getParam(Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_TOKEN))) {
            Mage::log("Unable to get a valid OAuth request token from MasterPass.", Zend_Log::WARN, self::LOGFILE);
            return null;
        }

        return $requestToken;
    }

    /**
     * Submit the customer's shopping cart to MasterPass in XML form.
     *
     * @param Zend_Oauth_Token_Request $requestToken
     * @return bool
     */
    protected function _submitShoppingCartXml(Zend_Oauth_Token_Request $requestToken)
    {
        $cartXml = $this->_checkout->generateCartXml($this->_getQuote());
        $cartXml->addChild(self::MPASS_CARTXML_OAUTHTOKEN, $requestToken->getParam(Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_TOKEN));

        $return = $this->_oauth->submitShoppingCartXml($cartXml);
        if ($return->getResponse()->getStatus() != self::RETURN_SUCCESS) {
            Mage::log("Invalid response from MasterPass when submitting shopping cart XML. Status of transaction: " . $return->getResponse()->getStatus(), Zend_Log::NOTICE, self::LOGFILE);
            return false;
        }
        return true;
    }

    /**
     * Determine where to redirect the customer when they click the "Buy with MasterPass" button.
     * If we received a valid redirect URL from MasterPass, we should redirect them there.
     * If not, take them back to the cart.
     *
     * @param Zend_Oauth_Token_Request $requestToken
     * @return $this
     */
    protected function _redirectToMasterpass(Zend_Oauth_Token_Request $requestToken)
    {
        $url = $this->_oauth->getRedirectUrl($requestToken, $this->_getQuote()->isVirtual());
        if ($url) {
            $this->_getCheckoutSession()->setData(self::MPASS_REQUEST_TOKEN, $requestToken); // We need this in the callback.
            $this->getResponse()->setRedirect($url);
            return $this;
        } else {
            Mage::log("Unable to determine MasterPass redirect URL.", Zend_Log::ERR, self::LOGFILE);
            $this->_getCheckoutSession()->addError($this->__("Sorry, we were unable to work out how to send you to %s. Please try again.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
            return $this->_redirect("checkout/cart");
        }
    }

    /**
     * Check whether or not the customer completed the MasterPass checkout successfully.
     *
     * @return bool
     */
    protected function _verifyCallback()
    {
        $queryData = $this->getRequest()->getQuery();

        if (empty($queryData[Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_TOKEN]) ||
            empty($queryData[Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_VERIFIER]) ||
            empty($queryData[Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_CHECKOUT_RES_URL])) {
            Mage::log("One or more of the required query string parameters were missing from the callback URL. Requested URL: " . $this->getRequest()->getRequestUri(), Zend_Log::NOTICE, self::LOGFILE);
            $this->_getCheckoutSession()->addError($this->__("It looks like you didn't successfully complete the %s checkout. Please try again.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()));
            return false;
        }

        return true;
    }

    /**
     * Retrieve the customer's payment data from MasterPass.
     * This involves getting an access token based on the request token used for the original request, and then using
     * this access token to retrieve the payment data.
     *
     * @return SimpleXMLElement|null
     */
    protected function _getMasterpassPaymentData()
    {
        // Get the original request token object used to initiate the MasterPass connection.
        $masterpassRequestToken = $this->_getCheckoutSession()->getData(self::MPASS_REQUEST_TOKEN);
        $this->_getCheckoutSession()->unsetData(self::MPASS_REQUEST_TOKEN);

        // Get the access token from MasterPass based on the original request token.
        $queryData = $this->getRequest()->getQuery();
        $accessToken = $this->_oauth->getAccessToken($queryData, $masterpassRequestToken);
        if (!$accessToken || $accessToken->getResponse()->getStatus() != self::RETURN_SUCCESS) {
            Mage::log("Invalid response from MasterPass when obtaining Access Token.", Zend_Log::WARN, self::LOGFILE);
            return null;
        }

        // Use the access token to get the customer's payment data from MasterPass.
        $paymentDataRequest = $this->_oauth->getPaymentData($accessToken, $queryData[Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_CHECKOUT_RES_URL]);
        if (!$paymentDataRequest || $paymentDataRequest->getResponse()->getStatus() != self::RETURN_SUCCESS) {
            Mage::log("Invalid response from MasterPass when retrieving customer payment data.", Zend_Log::WARN, self::LOGFILE);
            Mage::log("Checkout resource URL: " . $queryData[Fontis_Masterpass_Model_Masterpass_Oauth::OAUTH_CHECKOUT_RES_URL], Zend_Log::WARN, self::LOGFILE);
            return null;
        }

        // Verify that we got back valid XML from MasterPass.
        $paymentData = $paymentDataRequest->getResponse()->getBody();
        $paymentDataXml = simplexml_load_string($paymentData);
        if ($paymentDataXml === false) {
            Mage::log("Received malformed XML from MasterPass when retrieving customer payment data.", Zend_Log::NOTICE, self::LOGFILE);
            return null;
        }
        return $paymentDataXml;
    }

    /**
     * Sends the payment data returned from MasterPass off for processing, and attaches the processed data to the
     * customer's quote object in the checkout session.
     *
     * @param SimpleXMLElement $paymentDataXml
     * @return bool
     */
    protected function _attachMasterpassPaymentDataToQuote(SimpleXMLElement $paymentDataXml)
    {
        $quote = $this->_getQuote();
        $isVirtual = $quote->isVirtual();
        $quoteInfo = Mage::getModel("fontis_masterpass/masterpass_payment")->processPaymentData($paymentDataXml, $isVirtual);

        // Add the address information from the payment data to the quote.
        $this->_checkout->addDataToAddress($quote->getBillingAddress(), $quoteInfo["billingAddress"]);
        if ($isVirtual !== true) {
            $this->_checkout->addDataToAddress($quote->getShippingAddress(), $quoteInfo["shippingAddress"]);
        }

        // Add the actual payment data to the quote.
        $quoteInfo["payment"]["method"] = Mage::helper("fontis_masterpass/config")->getPaymentGateway();
        $this->_checkout->addPaymentData($quote->getPayment(), $quoteInfo["payment"]);
        // Save the credit card number to the customer's session, using Magento's encryption system.
        if (!Mage::helper('fontis_masterpass/config')->isTokenPaymentsAccepted()) {
            $this->_saveCcNumber($quoteInfo["payment"]["cc_number"]);
        }

        $quote->collectTotals()->save();

        // Attach the MasterPass Transaction ID to the customer's checkout session for use in later requests.
        $this->_getCheckoutSession()->setData(self::MPASS_TRANSACTION_ID, $quoteInfo["transactionId"]); // We need this in the callback.

        return true;
    }

    /**
     * Save an encrypted version of the customer's credit card number in their session.
     *
     * @param string $ccNumber
     */
    protected function _saveCcNumber($ccNumber)
    {
        $ccNumberEnc = Mage::helper("core")->encrypt($ccNumber);
        $this->_getCheckoutSession()->setData(Fontis_Masterpass_Helper_Data::MPASS_CC_NUMBER_ENC, $ccNumberEnc);
    }

    /**
     * Look for an encrypted credit card number in the customer's session. If it exists,
     * decrypt it and return it.
     *
     * @return string|null
     */
    protected function _getCcNumber()
    {
        $ccNumberEnc = $this->_getCheckoutSession()->getData(Fontis_Masterpass_Helper_Data::MPASS_CC_NUMBER_ENC);
        if (is_null($ccNumberEnc)) {
            return null;
        } else {
            return Mage::helper("core")->decrypt($ccNumberEnc);
        }
    }

    /**
     * Clear the customer's credit card number from their session.
     */
    protected function _clearCcNumber()
    {
        $this->_getCheckoutSession()->unsetData(Fontis_Masterpass_Helper_Data::MPASS_CC_NUMBER_ENC);
    }

    /**
     * Make a note of the fact that this is a MasterPass order.
     * It achieves this by putting a comment on the order, and by attaching a custom field to the payment object. This
     * custom field is stored in a serialised data field in the database, and is picked up by an appropriate block for
     * rendering.
     *
     * @see Fontis_Masterpass_Block_Adminhtml_Sales_Orderpayment
     * @param Mage_Sales_Model_Order $order
     */
    protected function _noteSuccessfulMasterpassOrder(Mage_Sales_Model_Order $order)
    {
        $transactionId = $this->_getCheckoutSession()->getData(self::MPASS_TRANSACTION_ID);

        $order->addStatusHistoryComment($this->__("This is a MasterPass order. Transaction ID: " . $transactionId));

        $order->getPayment()
            ->setAdditionalInformation("cc_from", "masterpass")
            ->setAdditionalInformation(Fontis_Masterpass_Helper_Data::MPASS_TXNID_LABEL, $transactionId)
            ->save();

        $order->save();
    }

    /**
     * Returns true after a successful postback.
     * Returns null if we never get a 200 OK response from MasterPass, or false if we did get
     * a response from MasterPass but it didn't match what we expected.
     *
     * @param Mage_Sales_Model_Order $order
     * @return bool|null
     */
    protected function _postCheckoutSuccessBackToMasterpass(Mage_Sales_Model_Order $order)
    {
        try {
            // Retrieve the MasterPass transaction ID from the customer's checkout session.
            $transactionId = $this->_getCheckoutSession()->getData(self::MPASS_TRANSACTION_ID);
            $this->_getCheckoutSession()->unsetData(self::MPASS_TRANSACTION_ID);

            // Retrieve key details from the customer's order object in XML form.
            $orderXml = $this->_checkout->generateOrderXml($order, $transactionId);
            // Insert the comsumer key into the XML.
            $orderXml->{Fontis_Masterpass_Model_Checkout::ORDERXML_GLOBAL}->addChild(self::MPASS_ORDERXML_CONSUMERKEY, Mage::helper("fontis_masterpass/config")->getConsumerKey());

            // POST the order XML to MasterPass.
            $orderPostbackRequest = $this->_oauth->submitTransactionPostback($orderXml);
            // If we don't get a 200 OK back from MasterPass, the postback didn't complete successfully.
            if (!$orderPostbackRequest || $orderPostbackRequest->getResponse()->getStatus() != self::RETURN_SUCCESS) {
                Mage::log("Invalid response from MasterPass when submitting order postback.", Zend_Log::WARN, self::LOGFILE);
                return null;
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            return null;
        }

        try {
            // Verify the response we got back from MasterPass.
            $orderPostback = $orderPostbackRequest->getResponse()->getBody();
            $orderPostbackXml = simplexml_load_string($orderPostback);
            if ($orderPostbackXml === false) {
                Mage::log("Received malformed XML from MasterPass when submitting order postback.", Zend_Log::NOTICE, self::LOGFILE);
                return false;
            }

            $check = Mage::getModel("fontis_masterpass/masterpass_order")->verifyOrderPostback($orderXml, $orderPostbackXml);
            if ($check === true) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage(), Zend_Log::ERR, self::LOGFILE);
            return false;
        }
    }

    /**
     * Make a note of the fact that a MasterPass order postback failed.
     * Under normal circumstances, this event should never occur.
     *
     * @param Mage_Sales_Model_Order $order
     */
    protected function _noteMasterpassPostbackFailure(Mage_Sales_Model_Order $order)
    {
        $order->addStatusHistoryComment($this->__("Unable to inform MasterPass that this order was successful."));
        $order->save();
    }
}
