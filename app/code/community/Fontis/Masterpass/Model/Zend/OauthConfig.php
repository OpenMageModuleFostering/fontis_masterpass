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

class Fontis_Masterpass_Model_Zend_OauthConfig extends Zend_Oauth_Config
{
    /**
     * Realm used in the Authorization header for Oauth requests.
     *
     * @var string
     */
    protected $_realm = null;

    /**
     * Parse option array or Zend_Config instance and setup options using their
     * relevant mutators.
     *
     * Overridden to allow the addition of the realm field to the Authorization header.
     *
     * @param  array|Zend_Config $options
     * @return Zend_Oauth_Config
     */
    public function setOptions(array $options)
    {
        if (array_key_exists("realm", $options)) {
            $this->_realm = $options["realm"];
        }
        parent::setOptions($options);
    }

    /**
     * Set realm.
     *
     * @param  string $key
     * @return Fontis_Masterpass_Model_Zend_OauthConfig
     */
    public function setRealm($key)
    {
        $this->_realm = $key;
        return $this;
    }

    /**
     * Get realm.
     *
     * @return string
     */
    public function getRealm()
    {
        return $this->_realm;
    }
}
