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

class Fontis_Masterpass_Model_Masterpass_Oauth extends Mage_Core_Model_Abstract
{
    const OAUTH_TOKEN               = Zend_Oauth_Token::TOKEN_PARAM_KEY;
    const OAUTH_VERIFIER            = "oauth_verifier";
    const OAUTH_CHECKOUT_RES_URL    = "checkout_resource_url";
    const OAUTH_CALLBACK_CONFIRMED  = Zend_Oauth_Token::TOKEN_PARAM_CALLBACK_CONFIRMED;
    const OAUTH_BODY_HASH           = "oauth_body_hash";
    const OAUTH_REQUEST_URL         = "xoauth_request_auth_url";

    /**
     * Method used to sign Oauth requests.
     * Currently MasterPass only supports RSA-SHA1.
     *
     * @var string
     */
    protected $_signatureMethod = "RSA-SHA1";

    /**
     * Oauth Version to use.
     * Both Zend_Oauth and MasterPass do not currently support anything other than v1.0.
     *
     * @var string
     */
    protected $_oauthVersion = "1.0";

    /**
     * Realm for Oauth request.
     * Used to differentiate between MasterPass mobile and desktop sites.
     * According to documentation, not currently used, but is required.
     *
     * @var string
     */
    protected $_oauthRealm = "eWallet";

    /**
     * XML Version to use for payment data being requested from MasterPass.
     * We always want to use v4, which is the most recent version MasterPass supports.
     * Must be of the format "vX" where X is the version number.
     *
     * @var string
     */
    protected $_xmlVersion = "v4";

    /**
     * Holds config values to pass to the Zend_Oauth module.
     *
     * @var array
     */
    protected $_oauthConfig = null;

    /**
     * @var Fontis_Masterpass_Helper_Data
     */
    protected $_helper = null;

    /**
     * @var Fontis_Masterpass_Helper_Config
     */
    protected $_configHelper = null;

    /**
     * Initialises the object with default configuration.
     */
    public function __construct()
    {
        $this->_helper = Mage::helper("fontis_masterpass");
        $this->_configHelper = Mage::helper("fontis_masterpass/config");
        $this->_oauthConfig = array(
            "consumerKey"       => $this->_configHelper->getConsumerKey(),
            "signatureMethod"   => $this->_signatureMethod,
            "version"           => $this->_oauthVersion,
            "accessTokenUrl"    => $this->_helper->getMasterpassOauthUrl("consumer/v1/access_token"),
            "rsaPrivateKey"     => new Zend_Crypt_Rsa_Key_Private($this->_configHelper->getPrivateKey(), ""),
            "realm"             => $this->_oauthRealm,
        );
    }

    /**
     * Make the initial request to MasterPass to get an OAuth token.
     * This token is used in future requests to the MasterPass API.
     *
     * @return Zend_Oauth_Token_Request
     */
    public function getRequestToken()
    {
        // Initialise the OAuth request.
        $config = array_merge($this->_oauthConfig, array(
            "callbackUrl"       => $this->_helper->getCallbackUrl(),
            "requestTokenUrl"   => $this->_helper->getMasterpassOauthUrl("consumer/v1/request_token"),
            "requestMethod"     => Zend_Oauth::POST,
        ));
        $consumer = new Fontis_Masterpass_Model_Zend_OauthConsumer($config);
        $request = new Fontis_Masterpass_Model_Zend_OauthRequestToken($consumer);
        $request->setMethod(Zend_Oauth::POST);

        // Make the request to MasterPass.
        $requestToken = $request->execute();
        return $requestToken;
    }

    /**
     * Submit shopping cart XML to MasterPass.
     *
     * @param SimpleXMLElement|string $cartXml the customer's cart in XML form, according to MasterPass specifications
     * @return Zend_Oauth_Token_Request
     */
    public function submitShoppingCartXml($cartXml)
    {
        return $this->submitXmlToMasterpass($cartXml, $this->_helper->getMasterpassOnlineUrl("v1/shopping-cart"));
    }

    /**
     * Construct the URL used to redirect a customer to MasterPass.
     *
     * @param Zend_Oauth_Token_Request $requestToken the RequestToken object created from the original MasterPass API call
     * @param bool $suppressShippingAddress whether or not the customer's cart contains just virtual and/or downloadable products
     * @return string|null
     */
    public function getRedirectUrl($requestToken, $suppressShippingAddress)
    {
        // Get primary redirect URL from request token.
        $url = $requestToken->getParam(self::OAUTH_REQUEST_URL);
        if (!$url) {
            return null;
        }

        // Add required query string parameters to the redirect URL.
        // eg. https://sandbox.masterpass.com/Checkout/Authorize?acceptable_cards=master,visa&checkout_identifier=mycheckoutid&oauth_token=mytoken&version=v4&suppress_shipping_address=false&accept_reward_program=false
        $queryParameters = array(
            "acceptable_cards"              => $this->_configHelper->getAcceptedCards(false),
            "checkout_identifier"           => $this->_configHelper->getCheckoutIdentifier(),
            "oauth_token"                   => $requestToken->getParam(self::OAUTH_TOKEN),
            "version"                       => $this->_xmlVersion,
            "suppress_shipping_address"     => $suppressShippingAddress ? "true" : "false", // For situations such as the cart containing all virtual items
            "accept_reward_program"         => "false", // We don't want MasterPass to deal with any vouchers or gift cards.
        );

        $queryString = http_build_query($queryParameters);
        return $url . "?" . $queryString;
    }

    /**
     * Get access token from MasterPass.
     * This token is later used to retrieve the customer's payment and shipping details from MasterPass.
     *
     * @param array $queryData the query string appended to the MasterPass callback URL by MasterPass
     * @param Zend_Oauth_Token_Request $requestToken the RequestToken object created from the original MasterPass API call
     * @return Zend_Oauth_Token_Access
     */
    public function getAccessToken($queryData, $requestToken)
    {
        // Initialise the OAuth request.
        $config = array_merge($this->_oauthConfig, array(
            "accessTokenUrl"    => $this->_helper->getMasterpassOauthUrl("consumer/v1/access_token"),
            "requestMethod"     => Zend_Oauth::POST,
        ));
        $consumer = new Fontis_Masterpass_Model_Zend_OauthConsumer($config);
        $request = new Fontis_Masterpass_Model_Zend_OauthAccessToken($consumer);
        $request->setMethod(Zend_Oauth::POST);

        // Make the request to MasterPass.
        $accessToken = $consumer->getAccessToken($queryData, $requestToken, Zend_Oauth::POST, $request);
        return $accessToken;
    }

    /**
     * @param Zend_Oauth_Token_Access $accessToken
     * @param string $checkoutResourceUrl the URL returned in the callback to retrieve the payment data from
     * @return Zend_Oauth_Token_Request
     */
    public function getPaymentData($accessToken, $checkoutResourceUrl)
    {
        // Initialise the OAuth request.
        $config = array_merge($this->_oauthConfig, array(
            "requestTokenUrl"   => $checkoutResourceUrl,
            "requestMethod"     => Zend_Oauth::GET,
        ));
        $consumer = new Fontis_Masterpass_Model_Zend_OauthConsumer($config);
        // We're using a RequestToken rather than an AccessToken because this request doesn't need
        // to be signed. It just needs an access token.
        $request = new Fontis_Masterpass_Model_Zend_OauthRequestToken($consumer);
        $request->setMethod(Zend_Oauth::GET);

        // Manually set the OAuth token for this request.
        $parameters = array(self::OAUTH_TOKEN => $accessToken->getToken());
        // MasterPass may tack on query parameters to the checkout resource URL. We need to retrieve
        // these and include them in the parameters used to generate the OAuth signature.
        parse_str(parse_url($checkoutResourceUrl, PHP_URL_QUERY), $queryParams);
        $request->setParameters(array_merge($parameters, $queryParams));

        // Initialise the OAuth HTTP client.
        $params = $request->assembleParams();
        $client = $request->getRequestSchemeHeaderClient($params);

        // Make the request to MasterPass.
        $response = $client->request();
        $return = new Zend_Oauth_Token_Request($response);
        return $return;
    }

    /**
     * Submit order XML to MasterPass.
     *
     * @param SimpleXMLElement|string $orderXml specific details about the customer's order in XML form, according to MasterPass specifications
     * @return Zend_Oauth_Token_Request
     */
    public function submitTransactionPostback($orderXml)
    {
        return $this->submitXmlToMasterpass($orderXml, $this->_helper->getMasterpassOnlineUrl("v2/transaction"));
    }

    /**
     * Generic function to submit XML to MasterPass.
     *
     * @param SimpleXMLElement|string $xml
     * @param string $url
     * @return Zend_Oauth_Token_Request
     */
    protected function submitXmlToMasterpass($xml, $url)
    {
        // Ensure the XML is in string form.
        if ($xml instanceof SimpleXMLElement) {
            $xml = $xml->asXML();
        }

        // Initialise the OAuth request.
        $config = array_merge($this->_oauthConfig, array(
            "requestTokenUrl"   => $url,
            "requestMethod"     => Zend_Oauth::POST,
        ));
        $consumer = new Fontis_Masterpass_Model_Zend_OauthConsumer($config);
        $request = new Fontis_Masterpass_Model_Zend_OauthRequestToken($consumer);
        $request->setMethod(Zend_Oauth::POST);
        // oauth_body_hash doesn't appear to be a standard OAuth field.
        // It is however something that MasterPass requires.
        $request->setParameters(array(self::OAUTH_BODY_HASH => $this->generateBodyHash($xml)));

        // Initialise the OAuth HTTP client.
        $params = $request->assembleParams();
        $client = $request->getRequestSchemeHeaderClient($params);
        $client->setRawData($xml);
        $client->setEncType("application/xml");

        // Make the request to MasterPass.
        $response = $client->request();
        $return = new Zend_Oauth_Token_Request($response);
        return $return;
    }

    /**
     * Generate hash from body content.
     * Taken from the MasterPass sample code.
     *
     * @param string $body
     * @return string
     */
    protected function generateBodyHash($body)
    {
        $sha1Hash = sha1($body, true);
        return base64_encode($sha1Hash);
    }
}
