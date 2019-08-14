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

class Fontis_Masterpass_Model_Directory_Region extends Mage_Directory_Model_Region
{
    /**
     * Proper function to lookup region based on country ID and region ID.
     * Magento's built-in functions are buggy and will only return a region if there is an
     * entry in directory_country_region_name for the locale of the current store. If it
     * can't find one, it won't return anything (not even the region with the "default_name"
     * field filled.
     *
     * @param string $region
     * @param string $countryId
     * @return Mage_Directory_Model_Region
     */
    public function lookupRegion($region, $countryId)
    {
        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton("core/resource");
        $connection = $resource->getConnection(Mage_Core_Model_Resource::DEFAULT_READ_RESOURCE);

        $select = $connection->select()
            ->from(array("region" => $resource->getTableName("directory/country_region")))
            ->where("region.country_id = ?", $countryId)
            ->where("region.code = ?", $region);
        $data = $connection->fetchRow($select);
        if (!empty($data)) {
            $this->setData($data);
        } else {
            return $this;
        }

        $locale = Mage::app()->getLocale()->getLocaleCode();
        $select = $connection->select()
            ->from(array("rname" => $resource->getTableName("directory/country_region_name")))
            ->columns("rname.name")
            ->where("rname.locale = ?", $locale)
            ->where("rname.region_id = ?", $this->getId());
        $name = $connection->fetchRow($select);
        if (!empty($name)) {
            $this->setName($name["name"]);
        }

        return $this;
    }
}
