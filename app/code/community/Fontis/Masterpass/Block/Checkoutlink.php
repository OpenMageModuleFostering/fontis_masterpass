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

class Fontis_Masterpass_Block_Checkoutlink extends Fontis_Masterpass_Block_Abstract
{
    const BASE_URL_SECURE   = "https://www.mastercard.com/mc_us/wallet/img/en/US/mcpp_wllt_btn_chk_";
    const BASE_URL          = "http://www.mastercard.com/mc_us/wallet/img/en/US/mcpp_wllt_btn_chk_";

    protected $checkoutButtonSizes = array(
        Fontis_Masterpass_Model_System_CheckoutButton::SIZE_SMALL   => "147x034px.png",
        Fontis_Masterpass_Model_System_CheckoutButton::SIZE_MEDIUM  => "180x042px.png",
        Fontis_Masterpass_Model_System_CheckoutButton::SIZE_LARGE   => "290x068px.png",
        Fontis_Masterpass_Model_System_CheckoutButton::SIZE_LARGEST => "360x084px.png",
    );

    /**
     * Whether the block should be eventually rendered.
     *
     * @var bool
     */
    protected $_shouldRender = true;

    /**
     * Check to see whether or not the button should be displayed.
     *
     * @return $this
     */
    protected function _beforeToHtml()
    {
        if (!$this->getMasterpassHelper()->isEnabled()) {
            $this->_shouldRender = false;
        }

        return parent::_beforeToHtml();
    }

    /**
     * Render the block if needed.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->_shouldRender) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Returns the URL that commences the MasterPass checkout process.
     * It should point to the "start" controller action in the Checkout Controller inside this MasterPass module.
     *
     * @return string
     */
    public function getCheckoutUrl()
    {
        return $this->getMasterpassHelper()->getCheckoutRedirectUrl();
    }

    /**
     * @param bool $useDefault force use of the default image
     * @param bool $secure force use of secure/non-secure image base URL
     * @return string
     */
    public function getImageUrl($useDefault = false, $secure = null)
    {
        if ($useDefault !== true) {
            $imageUrl = trim(Mage::helper("fontis_masterpass/config")->getCheckoutButtonUrl());
            if (!empty($imageUrl)) {
                return $imageUrl;
            }
        }

        if ($secure === true) {
            $url = self::BASE_URL_SECURE;
        } elseif ($secure === false) {
            $url = self::BASE_URL;
        } else {
            if (Mage::helper("fontis_masterpass")->isSecure() === true) {
                $url = self::BASE_URL_SECURE;
            } else {
                $url = self::BASE_URL;
            }
        }

        $url .= $this->getSize();

        return $url;
    }

    /**
     * Get the size the button should be displayed at.
     * If the size has not been set, it will return the default, which is the smallest available image.
     *
     * @return string
     */
    protected function getSize()
    {
        $size = (int) Mage::helper("fontis_masterpass/config")->getCheckoutButtonSize();
        if (array_key_exists($size, $this->checkoutButtonSizes)) {
            return $this->checkoutButtonSizes[$size];
        } else {
            return $this->checkoutButtonSizes[Fontis_Masterpass_Model_System_CheckoutButton::SIZE_SMALL];
        }
    }
}
