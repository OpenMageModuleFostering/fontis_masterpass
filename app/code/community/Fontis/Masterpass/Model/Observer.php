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

class Fontis_Masterpass_Model_Observer
{
    /**
     * Check to see if there is an encrypted credit card number in the user's session.
     * If there is, and we're not in the middle of a specific MasterPass request, clear
     * it from the session.
     *
     * Observes event "controller_action_predispatch".
     *
     * @param Varien_Event_Observer $observer
     */
    public function checkMasterpassDataInSession(Varien_Event_Observer $observer)
    {
        /** @var $this Mage_Core_Controller_Varien_Action */
        $controller = $observer->getEvent()->getControllerAction();
        if (!(Mage::helper("fontis_masterpass")->checkIfSensitiveRequest($controller->getRequest()))) {
            Mage::getSingleton("checkout/session")->unsetData(Fontis_Masterpass_Helper_Data::MPASS_CC_NUMBER_ENC);
        }
    }

    /**
     * Observes event "controller_action_predispatch_checkout_onepage_saveOrder".
     *
     * @param Varien_Event_Observer $observer
     */
    public function redirectUserToMasterpass(Varien_Event_Observer $observer)
    {
        $request = $observer->getEvent()->getControllerAction()->getRequest();
        if ($data = $request->getPost("payment", false)) {
            if ($data["method"] == Fontis_Masterpass_Model_Payment::PAYMENT_CODE) {
                $redirectUrl = Mage::helper("fontis_masterpass")->getCheckoutRedirectUrl();
                if ($observer->getEvent()->getDirectRedirect() === true) {
                    header('Location: ' . $redirectUrl);
                } else {
                    echo Mage::helper("core")->jsonEncode(array("redirect" => $redirectUrl));
                }
                exit;
            }
        }
    }
}
