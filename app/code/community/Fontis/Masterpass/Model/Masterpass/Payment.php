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

class Fontis_Masterpass_Model_Masterpass_Payment extends Mage_Core_Model_Abstract
{
    const PDATA_BILLING_GROUP       = "BillingAddress";
    const PDATA_SHIPPING_GROUP      = "ShippingAddress";
    const PDATA_PAYMENT_GROUP       = "Card";
    const PDATA_CUSTOMER_GROUP      = "Contact";
    const PDATA_TRANSACTIONID       = "TransactionId";

    const MAGE_ADDRESS_EMAIL        = "email";
    const MAGE_ADDRESS_FIRSTNAME    = "firstname";
    const MAGE_ADDRESS_MIDDLENAME   = "middlename";
    const MAGE_ADDRESS_LASTNAME     = "lastname";
    const MAGE_ADDRESS_PHONE        = "telephone";
    const MAGE_ADDRESS_STREET       = "street";

    const PDATA_PAYMENT_CARD_TYPE   = "BrandId";
    const MAGE_PAYMENT_CARD_TYPE    = "cc_type";

    /**
     * Maps MasterPass address fields to Magento quote address fields.
     *
     * @var array
     */
    protected $_addressFieldMapping = array(
        "City"                  => "city",
        "Country"               => "country_id",
        "CountrySubdivision"    => "region",
        "Line1"                 => "street1",
        "Line2"                 => "street2",
        "Line3"                 => "street3",
        "PostalCode"            => "postcode",
    );

    /**
     * Maps MasterPass credit card fields to Magento quote payment fields.
     *
     * @var array
     */
    protected $_paymentFieldMapping = array(
        "CardHolderName"        => "cc_owner",
        "AccountNumber"         => "cc_number",
        "ExpiryMonth"           => "cc_exp_month",
        "ExpiryYear"            => "cc_exp_year",
    );

    /**
     * @param SimpleXMLElement $paymentDataXml
     * @param bool $ignoreShippingAddress
     * @return array
     * @throws Exception
     * @throws Fontis_Masterpass_PaymentException
     */
    public function processPaymentData(SimpleXMLElement $paymentDataXml, $ignoreShippingAddress)
    {
        if (empty($paymentDataXml)) {
            throw new Exception("Cannot process empty XML object.");
        }

        $return = array();

        // Get the address and payment data necessary for Magento from the XML.
        $return["billingAddress"] = $this->extractBillingAddressFromPaymentData($paymentDataXml);
        $return["payment"] = $this->extractPaymentDetailsFromPaymentData($paymentDataXml);
        if ($ignoreShippingAddress !== true) {
            $return["shippingAddress"] = $this->extractShippingAddressFromPaymentData($paymentDataXml);
        }

        // Get the MasterPass transaction ID from the XML.
        $transactionIdXml = $paymentDataXml->{self::PDATA_TRANSACTIONID};
        if (empty($transactionIdXml)) {
            throw new Fontis_Masterpass_PaymentException("Transaction ID was missing from XML.");
        }
        $return["transactionId"] = (string) $transactionIdXml;

        return $return;
    }

    /**
     * Takes the full payment data XML (as a SimpleXMLElement object) retrieved from MasterPass and extracts
     * the billing address details from it.
     *
     * @param SimpleXMLElement $paymentDataXml the entire XML document retrieved from MasterPass
     * @return array
     * @throws Exception
     * @throws Fontis_Masterpass_PaymentException
     */
    public function extractBillingAddressFromPaymentData(SimpleXMLElement $paymentDataXml)
    {
        if (empty($paymentDataXml)) {
            throw new Exception("Cannot process empty XML object.");
        }

        $paymentData = $paymentDataXml->{self::PDATA_PAYMENT_GROUP};
        if (empty($paymentData)) {
            throw new Fontis_Masterpass_PaymentException("Payment data was missing from XML.");
        }
        $billingData = $paymentData->{self::PDATA_BILLING_GROUP};
        if (empty($billingData)) {
            throw new Fontis_Masterpass_PaymentException("Billing address data was missing from XML.");
        }
        $billingDataArray = $this->processAddressFields($billingData);

        $billingDataArray = $this->attachContactDetailsToBillingDataArray($billingDataArray, $paymentDataXml);

        return $billingDataArray;
    }

    /**
     * @param array $billingDataArray
     * @param SimpleXMLElement $paymentDataXml the entire XML document retrieved from MasterPass
     * @return array
     * @throws Exception
     * @throws Fontis_Masterpass_PaymentException
     */
    protected function attachContactDetailsToBillingDataArray(array $billingDataArray, SimpleXMLElement $paymentDataXml)
    {
        if (empty($paymentDataXml)) {
            throw new Exception("Cannot process empty XML object.");
        }

        $contactData = $paymentDataXml->{self::PDATA_CUSTOMER_GROUP};
        if (empty($contactData)) {
            throw new Fontis_Masterpass_PaymentException("Contact data was missing from XML.");
        }

        $billingDataArray[self::MAGE_ADDRESS_EMAIL] = (string) $contactData->EmailAddress;
        $billingDataArray[self::MAGE_ADDRESS_FIRSTNAME] = (string) $contactData->FirstName;
        $billingDataArray[self::MAGE_ADDRESS_LASTNAME] = (string) $contactData->LastName;
        $billingDataArray[self::MAGE_ADDRESS_PHONE] = (string) $contactData->PhoneNumber;

        $middleInitial = $contactData->MiddleInitial;
        if (!empty($middleInitial)) {
            $billingDataArray[self::MAGE_ADDRESS_MIDDLENAME] = (string) $middleInitial;
        }

        return $billingDataArray;
    }

    /**
     * Takes the full payment data XML (as a SimpleXMLElement object) retrieved from MasterPass and extracts
     * the shipping address details from it.
     *
     * @param SimpleXMLElement $paymentDataXml the entire XML document retrieved from MasterPass
     * @return array
     * @throws Exception
     * @throws Fontis_Masterpass_PaymentException
     */
    public function extractShippingAddressFromPaymentData(SimpleXMLElement $paymentDataXml)
    {
        if (empty($paymentDataXml)) {
            throw new Exception("Cannot process empty XML object.");
        }

        $shippingData = $paymentDataXml->{self::PDATA_SHIPPING_GROUP};
        if (empty($shippingData)) {
            throw new Fontis_Masterpass_PaymentException("Shipping address data was missing from XML.");
        }
        $shippingDataArray = $this->processAddressFields($shippingData);

        $shippingDataArray = $this->attachContactDetailsToShippingDataArray($shippingDataArray, $paymentDataXml);

        return $shippingDataArray;
    }

    /**
     * @param array $shippingDataArray
     * @param SimpleXMLElement $paymentDataXml the entire XML document retrieved from MasterPass
     * @return array
     * @throws Fontis_Masterpass_PaymentException
     */
    protected function attachContactDetailsToShippingDataArray(array $shippingDataArray, SimpleXMLElement $paymentDataXml)
    {
        $contactData = $paymentDataXml->{self::PDATA_CUSTOMER_GROUP};
        if (empty($contactData)) {
            throw new Fontis_Masterpass_PaymentException("Contact data was missing from XML.");
        }

        $shippingData = $paymentDataXml->{self::PDATA_SHIPPING_GROUP};
        if (empty($shippingData)) {
            throw new Fontis_Masterpass_PaymentException("Shipping address data was missing from XML.");
        }

        list($firstName, $middleName, $lastName) = $this->explodeName((string) $shippingData->RecipientName);

        $shippingDataArray[self::MAGE_ADDRESS_EMAIL] = (string) $contactData->EmailAddress;
        $shippingDataArray[self::MAGE_ADDRESS_FIRSTNAME] = $firstName;
        $shippingDataArray[self::MAGE_ADDRESS_MIDDLENAME] = $middleName;
        $shippingDataArray[self::MAGE_ADDRESS_LASTNAME] = $lastName;
        $shippingDataArray[self::MAGE_ADDRESS_PHONE] = (string) $shippingData->RecipientPhoneNumber;

        return $shippingDataArray;
    }

    /**
     * Splits a string up into three parts based on the number of words (names) in the string.
     * If there are three or more words (names) in the supplied string, all except the first and last word will be
     * placed into the middle element of the returned array.
     *
     * @param string $name
     * @return array
     */
    protected function explodeName($name)
    {
        $parts = explode(" ", $name);
        $count = count($parts);
        if ($count < 2) {
            // If the customer only entered one name, put it in the first name field.
            return array($parts[0], "", "");
        } elseif ($count == 2) {
            // If the customer entered two names, put them in the first and last name fields.
            return array($parts[0], "", $parts[1]);
        } else {
            // If the customer entered 3 or more names, put all but the first and last name into the middle name field.
            return array($parts[0], implode(" ", array_slice($parts, 1, ($count - 2))), $parts[($count - 1)]);
        }
    }

    /**
     * Turn address information from the MasterPass-supplied data into the address information
     * Magento expects.
     *
     * @param SimpleXMLElement $xmlNode
     * @return array
     */
    protected function processAddressFields(SimpleXMLElement $xmlNode)
    {
        $address = array(self::MAGE_ADDRESS_STREET => array());
        foreach ($this->_addressFieldMapping as $key => $field) {
            $tempField = $xmlNode->{$key};
            if (!empty($tempField)) {
                if (stripos($field, self::MAGE_ADDRESS_STREET) === false) {
                    $address[$field] = (string) $tempField;
                } else {
                    $address[self::MAGE_ADDRESS_STREET][] = (string) $tempField;
                }
            }
        }

        $address = $this->processRegion($address);
        return $address;
    }

    /**
     * Turn the region information from the MasterPass-supplied address into the region
     * information Magento expects.
     *
     * @param array $address
     * @return array
     */
    protected function processRegion(array $address)
    {
        if (!$address["region"]) {
            return $address;
        }

        // The region field may arrive from MasterPass in the form "AU-VIC" for countries that have set regions.
        // Check to see if we need to strip off the country code from the front.
        if (preg_match("/^[A-Z][A-Z]-[A-Z]+/", $address["region"])) {
            $region = explode("-", $address["region"]);
            $address["region"] = $region[1];
        }

        if (!$address["country_id"]) {
            return $address;
        }

        $regionModel = Mage::getModel("fontis_masterpass/directory_region")->lookupRegion($address["region"], $address["country_id"]);
        if ($id = $regionModel->getId()) {
            if ($name = $regionModel->getName()) {
                $address["region_id"]   = $id;
                $address["region"]      = $name;
            }
        }

        return $address;
    }

    /**
     * Takes the full payment data XML (as a SimpleXMLElement object) retrieved from MasterPass and extracts
     * the payment details from it.
     *
     * @param SimpleXMLElement $paymentDataXml the entire XML document retrieved from MasterPass
     * @return array
     * @throws Exception
     * @throws Fontis_Masterpass_PaymentException
     */
    public function extractPaymentDetailsFromPaymentData(SimpleXMLElement $paymentDataXml)
    {
        if (empty($paymentDataXml)) {
            throw new Exception("Cannot process empty XML object.");
        }

        $paymentData = $paymentDataXml->{self::PDATA_PAYMENT_GROUP};
        if (empty($paymentData)) {
            throw new Fontis_Masterpass_PaymentException("Payment data was missing from XML.");
        }
        $paymentDataArray = $this->processPaymentFields($paymentData);

        return $paymentDataArray;
    }

    /**
     * Turn the payment information from the MasterPass-supplied data into the payment
     * information Magento expects.
     *
     * @param SimpleXMLElement $xmlNode
     * @return array
     * @throws Fontis_Masterpass_PaymentException
     */
    protected function processPaymentFields(SimpleXMLElement $xmlNode)
    {
        $payment = array();
        foreach ($this->_paymentFieldMapping as $key => $field) {
            $tempField = $xmlNode->{$key};
            if (!empty($tempField)) {
                $payment[$field] = (string) $tempField;
            }
        }

        $ccType = $xmlNode->{self::PDATA_PAYMENT_CARD_TYPE};
        if (empty($ccType)) {
            throw new Fontis_Masterpass_PaymentException("Credit card type was missing from XML.");
        }
        $payment[self::MAGE_PAYMENT_CARD_TYPE] = Mage::getModel("fontis_masterpass/system_cards")->lookupMageCode((string) $ccType);

        return $payment;
    }
}
