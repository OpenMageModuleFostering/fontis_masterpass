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

class Fontis_Masterpass_Model_Zend_OauthAccessToken extends Zend_Oauth_Http_AccessToken
{
    /**
     * Generate and return a HTTP Client configured for the Header Request Scheme
     * specified by OAuth, for use in requesting an Access Token.
     *
     * Overridden so that the OAuth realm actually gets set.
     *
     * @param array $params
     * @return Zend_Http_Client
     */
    public function getRequestSchemeHeaderClient(array $params)
    {
        $client = parent::getRequestSchemeHeaderClient($params);
        $headerValue = $this->_httpUtility->toAuthorizationHeader(
            $params, $this->_consumer->getRealm()
        );
        $client->setHeaders("Authorization", $headerValue);
        return $client;
    }
}
