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

class Magmodules_Channableapi_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * @param $data
     *
     * @return bool|mixed
     */
    public function validateJsonOrderData($data)
    {
        $data = json_decode($data, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return false;
        }

        if (empty($data['channable_id'])) {
            return false;
        }

        if (empty($data['channel_id'])) {
            return false;
        }

        return $data;
    }

    /**
     * @param string $errors
     * @param string $orderId
     * @param string $channableId
     *
     * @return mixed
     */
    public function jsonResponse($errors = '', $orderId = '', $channableId = '')
    {
        $response = array();
        if (!empty($orderId)) {
            $response['validated'] = 'true';
            $response['order_id'] = $orderId;
        } else {
            $response['validated'] = 'false';
            $response['errors'] = $errors;
        }

        $logEnabled = Mage::getStoreConfig('channable_api/debug/log');
        if ($logEnabled) {
            if ($response['validated'] == 'true') {
                Mage::getModel('channableapi/debug')->orderSuccess($orderId);
            } else {
                Mage::getModel('channableapi/debug')->orderError($errors, $channableId);
            }
        }

        return $response;
    }

    /**
     * @param $productId
     *
     * @return string
     */
    public function getTestJsonData($productId)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        if ($product) {
            $string = '{"channable_id": 112345, "channel_id": 12345678, "channel_name": "Bol", "extra": {"memo": "Channable Test", "comment": "Channable order id: 999999999"}, "price": {"total": ' . $product->getFinalPrice() . ', "currency": "EUR", "shipping": 0, "subtotal": ' . $product->getFinalPrice() . ', "commission": 2.50, "payment_method": "bol", "transaction_fee": 0}, "billing": { "city": "Amsterdam", "email": "dontemail@me.net", "street": "Donkere Spaarne", "company": "Test company", "zip_code": "5000 ZZ", "last_name": "Channable", "first_name": "Test", "middle_name": "from", "country_code": "NL", "house_number": 100, "house_number_ext": "a", "address_supplement": "Onder de brievanbus huisnummer 1 extra adres info" }, "customer": { "email": "dontemail@me.net", "phone": "054333333", "gender": "man", "mobile": "", "company": "Test company", "last_name": "From Channable", "first_name": "Test", "middle_name": "" }, "products": [{"id": "' . $product->getEntityId() . '", "ean": "000000000", "price": ' . $product->getFinalPrice() . ', "title": "' . $product->getName() . '", "quantity": 1, "shipping": 0, "commission": 2.50, "reference_code": "00000000", "delivery_period": "2016-07-12+02:00"}], "shipping": {  "city": "Amsterdam", "email": "dontemail@me.net", "street": "Shipmentstraat", "company": "Magmodules", "zip_code": "1000 AA", "last_name": "from Channable", "first_name": "Test order", "middle_name": "", "country_code": "NL", "house_number": 21, "house_number_ext": "B", "address_supplement": "3 hoog achter extra adres info" }}';

            return $string;
        }

        return false;
    }

    /**
     * @return array|bool
     */
    public function checkIpRestriction()
    {
        $whitelisted = Mage::getStoreConfig('channable_api/general/whitelisted_ips');
        if (!empty($whitelisted)) {
            $ips = explode(',', $whitelisted);
            $ip = $_SERVER['REMOTE_ADDR'];
            if (!in_array($ip, $ips)) {
                $response = array();
                $response['validated'] = 'false';
                $response['errors'] = sprintf('IP: %s not on the whitelist', $ip);

                return $response;
            }
        }

        return false;
    }

    /**
     * @param $path
     *
     * @return array
     */
    public function getStoreIds($path)
    {
        $storeIds = array();
        foreach (Mage::app()->getStores() as $store) {
            $storeId = Mage::app()->getStore($store)->getId();
            if (Mage::getStoreConfig($path, $storeId)) {
                $storeIds[] = $storeId;
            }
        }

        return $storeIds;
    }

    /**
     * @return array
     */
    public function getEnabledItemStores()
    {
        $storeIds = array();
        foreach (Mage::app()->getStores() as $store) {
            $storeId = Mage::app()->getStore($store)->getId();
            $shopId = Mage::getStoreConfig('channable_api/item/enabled', $storeId);
            $webhook = Mage::getStoreConfig('channable_api/item/webhook', $storeId);
            if (!empty($shopId) && !empty($webhook)) {
                $storeIds[] = $storeId;
            }
        }

        return $storeIds;
    }

    /**
     * @param     $path
     * @param int $storeId
     *
     * @return mixed
     */
    public function getUncachedConfigValue($path, $storeId = 0)
    {
        $collection = Mage::getModel('core/config_data')->getCollection()->addFieldToFilter('path', $path);
        if ($storeId == 0) {
            $collection = $collection->addFieldToFilter('scope_id', 0)->addFieldToFilter('scope', 'default');
        } else {
            $collection = $collection->addFieldToFilter('scope_id', $storeId)->addFieldToFilter('scope', 'stores');
        }

        return $collection->getFirstItem()->getValue();
    }
}
