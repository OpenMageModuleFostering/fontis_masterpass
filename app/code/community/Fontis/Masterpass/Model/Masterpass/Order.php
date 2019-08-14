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

class Fontis_Masterpass_Model_Masterpass_Order extends Mage_Core_Model_Abstract
{
    /**
     * If the postback to MasterPass is successful, they return the same data back to us.
     * This function verifies that the returned data matches the order XML we generated.
     * Ideally, this function would be a straight string comparison. Unfortunately, we
     * can't guarantee that will work.
     *
     * @param SimpleXMLElement $orderXml
     * @param SimpleXMLElement $orderPostbackXml
     * @return bool
     */
    public function verifyOrderPostback(SimpleXMLElement $orderXml, SimpleXMLElement $orderPostbackXml)
    {
        $orderXmlCheck = $orderXml->{Fontis_Masterpass_Model_Checkout::ORDERXML_GLOBAL};
        $orderPostbackXmlCheck = $orderPostbackXml->{Fontis_Masterpass_Model_Checkout::ORDERXML_GLOBAL};
        if (empty($orderXmlCheck) || empty($orderPostbackXmlCheck)) {
            return false;
        }

        /**
         * We cannot guarantee that MasterPass will return the order date in the same format as:
         *  a) the format of the date we supplied
         *  b) the format they specified in their own documentation
         */
        $date1 = $orderXmlCheck->{Fontis_Masterpass_Model_Checkout::ORDERXML_DATE};
        $date2 = $orderPostbackXmlCheck->{Fontis_Masterpass_Model_Checkout::ORDERXML_DATE};
        if (empty($date1) || empty($date2)) {
            return false;
        }
        $date1 = strtotime($date1);
        $date2 = strtotime($date2);
        if ($date1 != $date2) {
            return false;
        }

        foreach ($orderXmlCheck->children() as $childName => $childNode) {
            if ($childName == Fontis_Masterpass_Model_Checkout::ORDERXML_DATE) {
                // We've already checked this.
                continue;
            }

            $temp1 = (string) $childNode;
            $tempNode = $orderPostbackXmlCheck->{$childName};
            if (empty($tempNode)) {
                return false;
            }
            $temp2 = (string) $tempNode;

            if ($temp1 != $temp2) {
                return false;
            }
        }

        return true;
    }
}
