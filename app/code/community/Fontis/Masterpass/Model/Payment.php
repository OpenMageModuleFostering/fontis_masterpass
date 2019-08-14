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

class Fontis_Masterpass_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    const PAYMENT_CODE = "fontis_masterpass";

    protected $_code = self::PAYMENT_CODE;

    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;

    protected $_formBlockType = "fontis_masterpass/payment_form";

    /**
     * @param $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return Mage::helper("fontis_masterpass")->canShowOnCheckout();
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return Mage::helper("fontis_masterpass/config")->getFrontendTitle();
    }

    /**
     * @param int $ignore
     * @return Fontis_Masterpass_Model_Payment
     */
    public function setSortOrder($ignore)
    {
        $this->setData("sort_order", Mage::helper("fontis_masterpass/config")->getPaymentSortOrder());
        return $this;
    }
}
