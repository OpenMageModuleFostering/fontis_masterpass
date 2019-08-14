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

class Fontis_Masterpass_Model_Checkout extends Mage_Core_Model_Abstract
{
    // Cart XML
    const CARTXML_GLOBAL        = "ShoppingCartRequest";
    const CARTXML_CART          = "ShoppingCart";
    const CARTXML_CURRENCY      = "CurrencyCode";
    const CARTXML_SUBTOTAL      = "Subtotal";
    const CARTXML_CARTITEM      = "ShoppingCartItem";
    const CARTXML_DESCRIPTION   = "Description";
    const CARTXML_QTY           = "Quantity";
    const CARTXML_VALUE         = "Value";
    const CARTXML_IMGURL        = "ImageURL";

    // Order XML
    const ORDERXML_GLOBAL       = "MerchantTransactions";
    const ORDERXML_TXNID        = "TransactionId";
    const ORDERXML_CONSUMERKEY  = "ConsumerKey";
    const ORDERXML_CURRENCY     = "Currency";
    const ORDERXML_AMOUNT       = "OrderAmount";
    const ORDERXML_DATE         = "PurchaseDate";
    const ORDERXML_STATUS       = "TransactionStatus";
    const ORDERXML_APPROVALCODE = "ApprovalCode";

    const ORDER_STATUS_SUCCESS  = "Success";
    const ORDER_STATUS_FAIL     = "Failure";

    const ORDER_APPROVALCODE_DEFAULT = "UNAVBL";

    /**
     * Turn the customer's shopping cart into an XML document.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return SimpleXmlElement
     */
    public function generateCartXml(Mage_Sales_Model_Quote $quote)
    {
        Mage::dispatchEvent("fontis_masterpass_generate_cartxml_before", array("quote" => $quote));

        //TODO: test with gift cards/voucher codes etc
        $shoppingCartXml = new SimpleXmlElement("<" . self::CARTXML_GLOBAL . "></" . self::CARTXML_GLOBAL . ">");

        $cart = $shoppingCartXml->addChild(self::CARTXML_CART);
        $cart->addChild(self::CARTXML_CURRENCY, $quote->getStoreCurrencyCode());
        $cart->addChild(self::CARTXML_SUBTOTAL, (int) bcmul($quote->getGrandTotal(), 100));

        // Using $quote->getAllVisibleItems() accounts for bundle products, configurable products, etc.
        foreach ($quote->getAllVisibleItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $node = $cart->addChild(self::CARTXML_CARTITEM);
            $node->addChild(self::CARTXML_DESCRIPTION, $product->getName());
            $node->addChild(self::CARTXML_QTY, $cartItem->getQty());
            $node->addChild(self::CARTXML_VALUE, (int) bcmul($product->getFinalPrice(), 100));
            $node->addChild(self::CARTXML_IMGURL, $product->getThumbnailUrl());
        }

        Mage::dispatchEvent("fontis_masterpass_generate_cartxml_after", array("quote" => $quote));

        return $shoppingCartXml;
    }

    /**
     * Set available address fields on the supplied address object.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param array $details
     * @throws Exception
     */
    public function addDataToAddress(Mage_Sales_Model_Quote_Address $address, array $details)
    {
        Mage::dispatchEvent("fontis_masterpass_add_address_data_before", array("address" => $address));

        foreach ($details as $key => $detail) {
            $address->setDataUsingMethod($key, $detail);
        }
        $address->implodeStreetAddress()->setCollectShippingRates(true);
        $validate = $address->validate();
        if ($validate !== true) {
            $message = "Failed to validate address after inserting address data.\n";
            $message .= "Validation errors:\n" . print_r($validate, true);
            $message .= "\nAddress details processed from XML retrieved from MasterPass:\n" . print_r($details, true);
            throw new Exception($message);
        }
        $address->save();

        Mage::dispatchEvent("fontis_masterpass_add_address_data_after", array("address" => $address));
    }

    /**
     * Set available payment fields on the supplied payment object.
     *
     * @param Mage_Sales_Model_Quote_Payment $payment
     * @param array $details
     */
    public function addPaymentData(Mage_Sales_Model_Quote_Payment $payment, array $details)
    {
        Mage::dispatchEvent("fontis_masterpass_add_payment_data_before", array("payment" => $payment));

        $payment->importData($details);

        Mage::dispatchEvent("fontis_masterpass_add_payment_data_after", array("payment" => $payment));
    }

    /**
     * Update the shipping method set on the customer's quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string $methodCode
     */
    public function updateShippingMethod(Mage_Sales_Model_Quote $quote, $methodCode)
    {
        Mage::dispatchEvent("fontis_masterpass_update_shipping_method_before", array("quote" => $quote, "shipping_method" => $methodCode));

        $changed = false;
        if (!$quote->getIsVirtual() && $shippingAddress = $quote->getShippingAddress()) {
            if ($methodCode != $shippingAddress->getShippingMethod()) {
                $shippingAddress->setShippingMethod($methodCode)->setCollectShippingRates(true);
                $quote->collectTotals()->save();
                $changed = true;
            }
        }

        Mage::dispatchEvent("fontis_masterpass_update_shipping_method_after", array("quote" => $quote, "shipping_method" => $methodCode, "changed" => $changed));
    }

    /**
     * All of the logic involved in placing an order in Magento.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return Mage_Sales_Model_Order
     */
    public function placeOrder(Mage_Sales_Model_Quote $quote)
    {
        Mage::dispatchEvent("fontis_masterpass_place_order_before", array("quote" => $quote));

        // Check to see if the customer is logged in or not.
        if (!$quote->getCustomerId()) {
            $quote->setCustomerIsGuest(true)
                ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
                ->setCustomerEmail($quote->getBillingAddress()->getEmail());
        }
        $quote->collectTotals();

        /** @var Mage_Sales_Model_Service_Quote $service */
        $service = Mage::getModel("sales/service_quote", $quote);

        // The Mage_Sales_Model_Service_Quote interface changed from v1.4.1.0 CE onwards.
        // This accounts for this change.
        if (method_exists($service, "submitAll")) {
            $service->submitAll();
            $order = $service->getOrder();
        } else {
            $order = $service->submit();
        }
        $order->save();

        Mage::dispatchEvent("fontis_masterpass_place_order_after", compact("quote", "order"));

        return $order;
    }

    /**
     * Turn the customer's order into an XML document.
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $transactionId
     * @return SimpleXmlElement
     */
    public function generateOrderXml(Mage_Sales_Model_Order $order, $transactionId)
    {
        Mage::dispatchEvent("fontis_masterpass_generate_orderxml_before", array("order" => $order));

        // This is not an accident. The specification requires the same tag nested inside itself.
        $orderXml = new SimpleXmlElement("<" . self::ORDERXML_GLOBAL . "></" . self::ORDERXML_GLOBAL . ">");
        $main = $orderXml->addChild(self::ORDERXML_GLOBAL);

        $main->addChild(self::ORDERXML_TXNID, $transactionId);
        $main->addChild(self::ORDERXML_CURRENCY, $order->getOrderCurrencyCode());
        $main->addChild(self::ORDERXML_AMOUNT, (int) bcmul($order->getGrandTotal(), 100));
        $main->addChild(self::ORDERXML_DATE, Mage::helper("fontis_masterpass")->formatDate($order->getCreatedAtStoreDate()->toValue()));
        $main->addChild(self::ORDERXML_STATUS, self::ORDER_STATUS_SUCCESS);
        // The default is used for everything because we don't know where the 6-digit approval code is supposed to come from.
        $main->addChild(self::ORDERXML_APPROVALCODE, self::ORDER_APPROVALCODE_DEFAULT);

        Mage::dispatchEvent("fontis_masterpass_generate_orderxml_after", array("order" => $order));

        return $orderXml;
    }
}
