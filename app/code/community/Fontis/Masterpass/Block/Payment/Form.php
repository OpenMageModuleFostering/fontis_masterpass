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

class Fontis_Masterpass_Block_Payment_Form extends Mage_Payment_Block_Form
{
    const LEARNMORE_BASEURL = "http://www.mastercard.com/mc_us/wallet/learnmore/";

    const MARK_BASE_URL_SECURE = "https://www.mastercard.com/mc_us/wallet/img/en/US/mp_acc_046px_gif.gif";
    const MARK_BASE_URL = "http://www.mastercard.com/mc_us/wallet/img/en/US/mp_acc_046px_gif.gif";
    const MARK_DEFAULT_HEIGHT = 32;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate("fontis/masterpass/payment/form.phtml");
    }

    public function getMethodLabelAfterHtml()
    {
        $language = Mage::helper("fontis_masterpass/config")->getLearnMoreLanguage();
        return '<a href="' . self::LEARNMORE_BASEURL . $language . '" target="_blank">' . Mage::helper("fontis_masterpass")->__("Learn more") . '</a>';
    }

    /**
     * @param bool $useDefault force use of the default image
     * @param bool $secure force use of secure/non-secure image base URL
     * @return string
     */
    public function getMarkImageUrl($useDefault = false, $secure = null)
    {
        if ($useDefault !== true) {
            $imageUrl = trim(Mage::helper("fontis_masterpass/config")->getAcceptanceMarkUrl());
            if (!empty($imageUrl)) {
                return $imageUrl;
            }
        }

        if ($secure === true) {
            $url = self::MARK_BASE_URL_SECURE;
        } elseif ($secure === false) {
            $url = self::MARK_BASE_URL;
        } else {
            if (Mage::helper("fontis_masterpass")->isSecure() === true) {
                $url = self::MARK_BASE_URL_SECURE;
            } else {
                $url = self::MARK_BASE_URL;
            }
        }

        return $url;
    }

    /**
     * This causes Magento to always use the getMethodTitle() function defined in this block.
     *
     * @return bool
     */
    public function hasMethodTitle()
    {
        return true;
    }

    /**
     * @return string
     */
    public function getMethodTitle()
    {
        if (!($size = Mage::helper("fontis_masterpass/config")->getAcceptanceMarkSize())) {
            $size = self::MARK_DEFAULT_HEIGHT;
        }
        $methodTitle = '<img src="' . $this->getMarkImageUrl() . '" alt="" class="v-middle" height="' . $size . 'px" /> &nbsp; ';
        $methodTitle .= '<span id="masterpass_method_title">' . Mage::helper("fontis_masterpass/config")->getFrontendTitle() . '</span>';
        return $methodTitle;
    }
}
