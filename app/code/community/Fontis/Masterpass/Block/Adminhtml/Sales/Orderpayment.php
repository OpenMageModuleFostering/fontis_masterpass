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

class Fontis_Masterpass_Block_Adminhtml_Sales_Orderpayment extends Mage_Adminhtml_Block_Sales_Order_Payment
{
    /**
     * Intercepts the rendering of the "Payment Information" box on the order view page in the admin panel.
     * Checks to see if there is MasterPass data on the quote object, and if so, display an appropriate message.
     *
     * @return string
     */
    protected function _toHtml()
    {
        $html = parent::_toHtml();
        $payment = $this->getData("payment");
        $info = $payment->getAdditionalInformation();
        if (!empty($info["cc_from"]) && $info["cc_from"] === "masterpass") {
            $html .= "<br />" . $this->__("The details for this order came from %s.", Mage::helper("fontis_masterpass/config")->getFrontendTitle()) . "<br />";
            if (!empty($info[Fontis_Masterpass_Helper_Data::MPASS_TXNID_LABEL])) {
                $html .= "MasterPass transaction ID: " . $info[Fontis_Masterpass_Helper_Data::MPASS_TXNID_LABEL] . "<br />";
            }
        }
        return $html;
    }
}
