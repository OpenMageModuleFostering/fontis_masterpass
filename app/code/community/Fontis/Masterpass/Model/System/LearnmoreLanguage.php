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

class Fontis_Masterpass_Model_System_LearnmoreLanguage
{
    const LANG_ENGLISH = "en";
    const LANG_SWEDISH = "se";
    const LANG_FRENCH  = "fr";
    const LANG_ITALIAN = "it";
    const LANG_SPANISH = "es";

    protected $languages = array(
        self::LANG_ENGLISH => "English",
        self::LANG_SWEDISH => "Swedish",
        self::LANG_FRENCH  => "French",
        self::LANG_ITALIAN => "Italian",
        self::LANG_SPANISH => "Spanish",
    );

    public function toOptionArray()
    {
        $options = array();
        $helper = Mage::helper("fontis_masterpass");
        foreach ($this->languages as $code => $language) {
            $options[] = array(
                "label" => $helper->__($language),
                "value" => $code,
            );
        }
        return $options;
    }
}
