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
 * @copyright     Copyright (c) 2018 (http://www.magmodules.eu)
 * @license       http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Magmodules_Channableapi_Helper_Data extends Mage_Core_Helper_Abstract
{

    const FEED_MIN_REQUIREMENT = '1.6.0';
    const LOG_FILENAME = 'channable-api.log';
    const XPATH_ENABLED = 'channable_api/general/enabled';
    const XPATH_WEBHOOK_ITEM = 'channable_api/item/webhook';
    const XPATH_TOKEN = 'channable/connect/token';
    const XPATH_ITEM_ENABLED = 'channable_api/item/enabled';
    const XPATH_ORDER_ENABLED = 'channable_api/order/enabled';
    const XPATH_ITEM_RESULT = 'channable_api/item/result';
    const XPATH_WHITELISTED = 'channable_api/general/whitelisted_ips';
    const XPATH_CRON_FREQUENCY = 'channable_api/crons/frequency';

    /**
     * @return mixed
     */
    public function getEnabled()
    {
        return Mage::getStoreConfig(self::XPATH_ENABLED);
    }

    /**
     * @param $orderData
     * @param $request
     *
     * @return mixed|string
     */
    public function validateJsonOrderData($orderData, $request)
    {
        $data = null;
        $test = $request->getParam('test');
        $lvb = $request->getParam('lvb');
        $storeId = $request->getParam('store');

        if ($test) {
            $data = $this->getTestJsonData($test, $lvb);
        } else {
            if ($orderData == null) {
                return $this->jsonResponse('Post data empty!');
            }
            $data = json_decode($orderData, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                return $this->jsonResponse('Post not valid JSON-Data: ' . json_last_error_msg());
            }
        }

        if (empty($data)) {
            return $this->jsonResponse('No Order Data in post');
        }

        if (empty($data['channable_id'])) {
            return $this->jsonResponse('Post missing channable_id');
        }

        if (empty($data['channel_id'])) {
            return $this->jsonResponse('Post missing channel_id');
        }

        if (!empty($data['order_status'])) {
            if ($data['order_status'] == 'shipped') {
                if (!Mage::getStoreConfig('channable_api/advanced/lvb', $storeId)) {
                    return $this->jsonResponse('LVB Orders not enabled');
                }
            }
        }

        return $data;
    }

    /**
     * @param      $productId
     * @param bool $lvb
     *
     * @return bool|mixed
     */
    public function getTestJsonData($productId, $lvb = false)
    {
        $orderStatus = $lvb ? 'shipped' : 'not_shipped';
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($productId);
        if ($product) {
            $data = '{"channable_id": 112345, "channel_id": 123456, "channel_name": "Bol", 
              "order_status": "' . $orderStatus . '", "extra": {"memo": "Channable Test", 
              "comment": "Channable order id: 999999999"}, "price": {"total": "' . $product->getFinalPrice() . '", 
              "currency": "EUR", "shipping": 0, "subtotal": "' . $product->getFinalPrice() . '",
              "commission": 2.50, "payment_method": "bol", "transaction_fee": 0},
              "billing": { "city": "Amsterdam", "state": "", "email": "dontemail@me.net",
              "address_line_1": "Billing Line 1", "address_line_2": "Billing Line 2", "street": "Donkere Spaarne", 
              "company": "Test company", "zip_code": "5000 ZZ", "last_name": "Channable", "first_name": "Test",
              "middle_name": "from", "country_code": "NL", "house_number": 100, "house_number_ext": "a",
              "address_supplement": "Address supplement" }, "customer": { "email": "dontemail@me.net", 
              "phone": "054333333", "gender": "man", "mobile": "", "company": "Test company", "last_name":
              "From Channable", "first_name": "Test", "middle_name": "" },
              "products": [{"id": "' . $product->getEntityId() . '", "ean": "000000000", 
              "price": "' . $product->getFinalPrice() . '", "title": "' . htmlentities($product->getName()) . '", 
              "quantity": 1, "shipping": 0, "commission": 2.50, "reference_code": "00000000", 
              "delivery_period": "2016-07-12+02:00"}], "shipping": {  "city": "Amsterdam", "state": "", 
              "email": "dontemail@me.net", "street": "Shipping Street", "company": "Magmodules",
              "zip_code": "1000 AA", "last_name": "from Channable", "first_name": "Test order", "middle_name": "",
              "country_code": "NL", "house_number": 21, "house_number_ext": "B", "address_supplement": 
              "Address Supplement", "address_line_1": "Shipping Line 1", "address_line_2": "Shipping Line 2" }}';

            return json_decode($data, true);
        }

        return false;
    }

    /**
     * @param string $errors
     * @param string $orderId
     * @param string $channableId
     *
     * @return mixed
     */
    public function jsonResponse($errors = null, $orderId = null, $channableId = null)
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
            /** @var Magmodules_Channableapi_Model_Debug $debugModel */
            $debugModel = Mage::getModel('channableapi/debug');
            if ($response['validated'] == 'true') {
                $debugModel->orderSuccess($orderId);
            } else {
                $debugModel->orderError($errors, $channableId);
            }
        }

        return $response;
    }

    /**
     * @param $returnData
     * @param $request
     *
     * @return mixed|string
     */
    public function validateJsonReturnData($returnData, $request)
    {
        $data = null;

        if ($returnData == null) {
            return $this->jsonResponse('Post data empty!');
        }

        $data = json_decode($returnData, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return $this->jsonResponse('Post not valid JSON-Data: ' . json_last_error_msg());
        }

        $storeId = $request->getParam('store');
        if (empty($storeId)) {
            return $this->jsonResponse('Missing Store ID in request');
        }

        if (empty($data)) {
            return $this->jsonResponse('No Order Data in post');
        }

        if (empty($data['channable_id'])) {
            return $this->jsonResponse('Post missing channable_id');
        }

        if (empty($data['channel_id'])) {
            return $this->jsonResponse('Post missing channel_id');
        }

        return $data;
    }

    /**
     * @param $data
     *
     * @return mixed|string
     */
    public function validateJsonWebhookData($data)
    {
        $data = json_decode($data, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return $this->jsonResponse('Post not valid JSON-Data: ' . json_last_error_msg());
        }

        if (empty($data)) {
            return $this->jsonResponse('No data in post');
        }

        if (!isset($data['webhook'])) {
            return $this->jsonResponse('Post missing webhook');
        }

        return $data['webhook'];
    }

    /**
     * @param        $request
     * @param string $type
     *
     * @return bool|mixed
     */
    public function validateRequestData($request, $type = 'order')
    {
        if ($ipCheck = $this->checkIpRestriction()) {
            return $this->jsonResponse('Not Access (ip restriction)');
        }

        $storeId = $request->getParam('store');
        if (empty($storeId)) {
            return $this->jsonResponse('Store param missing in request');
        }

        $enabled = Mage::getStoreConfig(self::XPATH_ENABLED);
        if (empty($enabled)) {
            return $this->jsonResponse('Extension not enabled');
        }

        if ($type == 'order') {
            $order = Mage::getStoreConfig(self::XPATH_ORDER_ENABLED, $storeId);
            if (empty($order)) {
                return $this->jsonResponse('Order import not enabled');
            }
        }

        $token = $this->getToken();
        if (empty($token)) {
            return $this->jsonResponse('Token not set in admin');
        }

        $code = $request->getParam('code');
        if (empty($code)) {
            return $this->jsonResponse('Token param missing in request');
        }

        if ($code != $token) {
            return $this->jsonResponse('Invalid token');
        }

        return false;
    }

    /**
     * @return array|bool
     */
    public function checkIpRestriction()
    {
        $whitelisted = Mage::getStoreConfig(self::XPATH_WHITELISTED);
        if (!empty($whitelisted)) {
            $ips = explode(',', $whitelisted);
            /** @var Mage_Core_Helper_Http $coreHelper */
            $coreHelper = Mage::helper('core/http');
            $ip = $coreHelper->getRemoteAddr(true);
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
     * @return mixed
     */
    public function getToken()
    {
        return Mage::getStoreConfig(self::XPATH_TOKEN);
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
            $storeId = $store->getId();
            if (Mage::getStoreConfig($path, $storeId)) {
                $storeIds[] = $storeId;
            }
        }

        return $storeIds;
    }

    /**
     * @param bool $webhookCheck
     *
     * @return array
     */
    public function getEnabledItemStores($webhookCheck = true)
    {
        $storeIds = array();
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $enabled = $this->getItemEnabled($storeId);

            if ($webhookCheck) {
                $webhook = $this->getItemUpdateWebhook($storeId);
            } else {
                $webhook = true;
            }

            if ($enabled && !empty($webhook)) {
                $storeIds[] = $storeId;
            }
        }

        return $storeIds;
    }

    /**
     * @param $storeId
     *
     * @return mixed
     */
    public function getItemEnabled($storeId)
    {
        if (!$this->isChannableInstalled()) {
            return false;
        }

        return Mage::getStoreConfig(self::XPATH_ITEM_ENABLED, $storeId);
    }

    /**
     * @return bool
     */
    public function isChannableInstalled()
    {
        $modules = Mage::getConfig()->getNode('modules')->children();
        $modulesArray = (array)$modules;

        if (isset($modulesArray['Magmodules_Channable'])) {
            return true;
        }

        return false;
    }

    /**
     * @param      $storeId
     * @param bool $returnEmpty
     *
     * @return mixed
     */
    public function getItemUpdateWebhook($storeId, $returnEmpty = false)
    {
        $webhook = Mage::getStoreConfig(self::XPATH_WEBHOOK_ITEM, $storeId);

        if (empty($webhook) && $returnEmpty) {
            $webhook = '<i>' . $this->__('-- not set --') . '</i>';
        }

        return $webhook;
    }

    /**
     * @param      $storeId
     * @param bool $returnEmpty
     *
     * @return string
     */
    public function getOrderWebhook($storeId, $returnEmpty = false)
    {
        $webhook = '';
        if (!$this->isChannableInstalled()) {
            return $webhook;
        }

        $token = $this->getToken();
        $isSecure = $this->getIsSecure($storeId);

        if ($token) {
            $params = array('_store' => $storeId, '_secure' => $isSecure, '_nosid' => true);
            $baseUrl = Mage::getUrl('channableapi/order/index', $params);
            $baseUrl = strtok($baseUrl, '?');
            $webhook = $baseUrl . 'code/' . $token . '/store/' . $storeId;
        }

        if (empty($webhook) && $returnEmpty) {
            $webhook = $this->__('Missing token');
        }

        return $webhook;
    }

    /**
     * @param $storeId
     *
     * @return mixed
     */
    public function getIsSecure($storeId)
    {
        return Mage::getStoreConfig('web/secure/use_in_frontend', $storeId);
    }

    /**
     * @param $storeId
     *
     * @return mixed
     */
    public function getOrderEnabled($storeId)
    {
        if (!$this->isChannableInstalled()) {
            return false;
        }

        return Mage::getStoreConfig(self::XPATH_ORDER_ENABLED, $storeId);
    }

    /**
     * @param $storeId
     *
     * @return mixed
     */
    public function getItemResults($storeId)
    {
        return $this->getUncachedConfigValue(self::XPATH_ITEM_RESULT, $storeId);
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

    /**
     * @param $url
     * @param $storeId
     *
     * @return array
     */
    public function setWebhook($url, $storeId)
    {
        $response = array();
        $response['validated'] = 'true';

        /** @var Mage_Core_Model_Config $config */
        $config = Mage::getModel('core/config');
        if (empty($url)) {
            $config->saveConfig(self::XPATH_WEBHOOK_ITEM, '', 'stores', $storeId);
            $config->saveConfig(self::XPATH_ITEM_ENABLED, 0, 'stores', $storeId);
            $response['msg'] = sprintf('Removed webhook and disabled update', $url);
        } else {
            $config->saveConfig(self::XPATH_WEBHOOK_ITEM, $url, 'stores', $storeId);
            $config->saveConfig(self::XPATH_ITEM_ENABLED, 1, 'stores', $storeId);
            $response['msg'] = sprintf('Webhook set to %s', $url);
        }

        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();

        return $response;
    }

    /**
     * @return mixed
     */
    public function getModuleVersion()
    {
        return Mage::getConfig()->getNode('modules')->children()->Magmodules_Channableapi->version;
    }

    /**
     * @return string
     */
    public function getMagentoVersion()
    {
        return Mage::getVersion();
    }

    /**
     * @return mixed
     */
    public function getCronExpression()
    {
        return Mage::getStoreConfig(self::XPATH_CRON_FREQUENCY);
    }

    /**
     * @param $path
     * @param $params
     *
     * @return string
     */
    public function getBackendUrl($path, $params = array())
    {
        /** @var Mage_Adminhtml_Helper_Data $helper */
        $helper = Mage::helper("adminhtml");
        return $helper->getUrl($path, $params);
    }

    /**
     * @param      $type
     * @param      $msg
     * @param int  $level
     * @param bool $force
     */
    public function addToLog($type, $msg, $level = 6, $force = false)
    {
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }

        Mage::log($type . ': ' . $msg, $level, self::LOG_FILENAME, $force);
    }

}
