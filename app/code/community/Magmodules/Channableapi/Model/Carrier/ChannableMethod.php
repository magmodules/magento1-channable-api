<?php
/**
 * Magmodules.eu - http://www.magmodules.eu
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@magmodules.eu so we can send you a copy immediately.
 *
 * @category      Magmodules
 * @package       Magmodules_Channableapi
 * @author        Magmodules <info@magmodules.eu>
 * @copyright     Copyright (c) 2017 (http://www.magmodules.eu)
 * @license       http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Magmodules_Channableapi_Model_Carrier_ChannableMethod extends Mage_Shipping_Model_Carrier_Abstract
{

    protected $_code = 'channableapi';

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     *
     * @return bool
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (Mage::getSingleton('core/session')->getChannableEnabled() != '1') {
            return false;
        }

        if (Mage::getSingleton('core/session')->getChannableShipping() > 0) {
            $price = Mage::getSingleton('core/session')->getChannableShipping();
        } else {
            $price = '0.00';
        }

        $result = Mage::getModel('shipping/rate_result');
        $method = Mage::getModel('shipping/rate_result_method');

        $method->setCarrier('channableapi');
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod('channableapi');
        $method->setMethodTitle($this->getConfigData('name'));
        $method->setPrice($price);
        $method->setCost('0.00');
        $result->append($method);

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return array($this->_code => $this->getConfigData('name'));
    }

}