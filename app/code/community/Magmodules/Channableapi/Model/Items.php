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

class Magmodules_Channableapi_Model_Items extends Mage_Core_Model_Abstract
{

    /**
     * Construct
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('channableapi/items');
    }

    /**
     * @param $productData
     * @param $storeId
     */
    public function saveItemFeed($productData, $storeId)
    {
        $id = $storeId . sprintf('%08d', $productData['id']);

        if (isset($productData['price'])) {
            if (!empty($productData['special_price'])) {
                $price = $productData['price'];
                $discountPrice = $productData['special_price'];
            } else {
                $price = $productData['price'];
                $discountPrice = '';
            }

            $deliveryCostNl = '';
            $deliveryCostBe = '';
            if (!empty($productData['shipping'])) {
                $deliveryCostNl = $productData['shipping'];
                $deliveryCostBe = $productData['shipping'];
            }

            if (!empty($productData['shipping_nl'])) {
                $deliveryCostNl = $productData['shipping_nl'];
            }

            if (!empty($productData['shipping_be'])) {
                $deliveryCostBe = $productData['shipping_be'];
            }

            $deliveryTimeNl = '';
            $deliveryTimeBe = '';
            if (!empty($productData['delivery'])) {
                $deliveryTimeNl = $productData['delivery'];
            }

            if (!empty($productData['delivery_be'])) {
                $deliveryTimeBe = $productData['delivery_be'];
            }

            $qty = 0;
            if (!empty($productData['qty'])) {
                $qty = $productData['qty'];
            }

            $isInStock = 1;
            if (!empty($productData['is_in_stock'])) {
                $isInStock = $productData['is_in_stock'];
            }

            $this->setItemId($id)
                ->setProductTitle($productData['name'])
                ->setProductId($productData['id'])
                ->setStoreId($storeId)
                ->setPrice($price)
                ->setQty($qty)
                ->setIsInStock($isInStock)
                ->setDiscountPrice($discountPrice)
                ->setDeliveryCostNl($deliveryCostNl)
                ->setDeliveryCostBe($deliveryCostBe)
                ->setDeliveryTimeNl($deliveryTimeNl)
                ->setDeliveryTimeBe($deliveryTimeBe)
                ->setCreatedAt(now())
                ->setUpdatedAt(now())
                ->save();
        }
    }

    /**
     * @param      $productId
     * @param      $type
     * @param null $reason
     * @param null $parent
     * @param int  $storeId
     */
    public function invalidateProduct($productId, $type, $reason = null, $parent = null, $storeId = 0)
    {
        $items = $this->getCollection()->addFieldToFilter('product_id', $productId);

        if ($storeId != 0) {
            $items->addFieldToFilter('store_id', $storeId);
        }

        $log = Mage::getStoreConfig('channable_api/debug/log');
        foreach ($items->load() as $item) {
            $item->setNeedsUpdate('1')->setUpdatedAt(now())->save();
            if ($log) {
                $message = 'Scheduled for item update, reason: ' . $reason;
                Mage::getModel('channableapi/debug')->addToLog('Item Update', $type, $productId, $message, $parent);
            }
        }
    }

    /**
     * @param        $storeId
     * @param string $items
     *
     * @return mixed
     */
    public function runUpdate($storeId, $items = '')
    {
        $config = $this->getConfig($storeId);
        if (empty($items)) {
            $items = $this->getCollection()
                ->addFieldToFilter('store_id', $storeId)
                ->setPageSize($config['limit'])
                ->setCurPage(1)
                ->addFieldToFilter('needs_update', 1)
                ->load();
        }

        if (!empty($items)) {
            $data = $this->processItems($items, $storeId, $config);
            $results = $this->postItemUpdate($data, $config);
            $updates = $this->updateItemRows($data, $results);

            return $updates;
        }

        return false;
    }

    /**
     * @param $storeId
     *
     * @return array
     */
    public function getConfig($storeId)
    {
        $config = array();
        $config['store_id'] = $storeId;
        $config['shipping_prices'] = @unserialize(Mage::getStoreConfig('channable/advanced/shipping_price', $storeId));
        $config['shipping_method'] = Mage::getStoreConfig('channable/advanced/shipping_method', $storeId);
        $config['delivery'] = Mage::getStoreConfig('channable/data/delivery', $storeId);
        $config['delivery_be'] = Mage::getStoreConfig('channable/data/delivery_be', $storeId);
        $config['delivery_att'] = Mage::getStoreConfig('channable/data/delivery_att', $storeId);
        $config['delivery_att_be'] = Mage::getStoreConfig('channable/data/delivery_att_be', $storeId);
        $config['delivery_in'] = Mage::getStoreConfig('channable/data/delivery_in', $storeId);
        $config['delivery_in_be'] = Mage::getStoreConfig('channable/data/delivery_in_be', $storeId);
        $config['delivery_out'] = Mage::getStoreConfig('channable/data/delivery_out', $storeId);
        $config['delivery_out_be'] = Mage::getStoreConfig('channable/data/delivery_out_be', $storeId);
        $config['price_add_tax'] = Mage::getStoreConfig('channable/data/add_tax', $storeId);
        $config['price_add_tax_perc'] = Mage::getStoreConfig('channable/data/tax_percentage', $storeId);
        $config['force_tax'] = Mage::getStoreConfig('channable/data/force_tax', $storeId);
        $config['currency'] = Mage::app()->getStore($storeId)->getCurrentCurrencyCode();
        $config['base_currency_code'] = Mage::app()->getStore($storeId)->getBaseCurrencyCode();
        $config['markup'] = Mage::helper('channable')->getPriceMarkup($config);
        $config['use_tax'] = Mage::helper('channable')->getTaxUsage($config);
        $config['webhook'] = Mage::getStoreConfig('channable_api/item/webhook', $storeId);
        $config['limit'] = Mage::getStoreConfig('channable_api/crons/limit', $storeId);
        $config['token'] = Mage::getStoreConfig('channable/connect/token', $storeId);
        $config['debug'] = Mage::getStoreConfig('channable_api/debug/debug');
        $config['log'] = Mage::getStoreConfig('channable_api/debug/log');
        $config['conf_enabled'] = Mage::getStoreConfig('channable/data/conf_enabled', $storeId);
        $config['simple_price'] = Mage::getStoreConfig('channable/data/simple_price', $storeId);

        if ($config['conf_enabled']) {
            $fields = Mage::getStoreConfig('channable/data/conf_fields', $storeId);
            $fields = explode(',', $fields);
            if (in_array('name', $fields)) {
                $config['parent_name'] = 1;
            }
        }

        $attributes = array('name', 'status', 'weight');

        if ($config['delivery'] == 'attribute') {
            $attributes[] = $config['delivery_att'];
        }

        if ($config['delivery_be'] == 'attribute') {
            $attributes[] = $config['delivery_att_be'];
        }

        $config['attributes'] = array_unique($attributes);

        if (empty($config['limit'])) {
            $config['limit'] = 20;
        }

        if ($config['limit'] > 25) {
            $config['limit'] = 25;
        }

        return $config;
    }

    /**
     * @param        $items
     * @param        $storeId
     * @param string $config
     *
     * @return array
     */
    public function processItems($items, $storeId, $config = '')
    {
        $data = array();
        if (empty($config)) {
            $config = $this->getConfig($storeId);
        }

        foreach ($items as $item) {
            $data[] = $this->getItemData($item, $config);
        }

        return $data;
    }

    /**
     * @param $productId
     * @param $storeId
     * @param $config
     *
     * @return mixed
     */
    public function getProductData($productId, $storeId, $config)
    {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->setStore($storeId)
            ->addStoreFilter($storeId)
            ->addFinalPrice()
            ->addUrlRewrite()
            ->addAttributeToFilter('entity_id', $productId)
            ->addAttributeToSelect($config['attributes'])
            ->getFirstItem();

        return $product;
    }

    /**
     * @param $item
     * @param $config
     *
     * @return array
     */
    public function getItemData($item, $config)
    {
        $update = array();
        $product = $this->getProductData($item->getProductId(), $item->getStoreId(), $config);

        if (!$product->getEntityId()) {
            $update['item_id'] = $item->getItemId();
            $update['id'] = $item->getProductId();
            $update['title'] = $item->getProductTitle();
            $update['stock'] = 0;
            $update['availability'] = 0;
            $update['price'] = $item->getPrice();
            $update['discount_price'] = $item->getDiscountPrice();
            $update['delivery_period_nl'] = 'out of stock';
            $update['delivery_period_be'] = 'out of stock';
            $update['shipping_price_nl'] = $item->getDeliveryCostNl();
            $update['shipping_price_be'] = $item->getDeliveryCostBe();
            return $update;
        }

        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
        if ($parentId = Mage::helper('channable')->getParentData($product, $config)) {
            $parent = $this->getProductData($parentId, $item->getStoreId(), $config);
            $confPrices = Mage::helper('channable')->getTypePrices($config, array($parent));
        } else {
            $confPrices = '';
            $parentId = '';
        }

        $update['item_id'] = $item->getItemId();
        $update['id'] = $product->getEntityId();
        $update['title'] = $product->getName();
        $update['created'] = $product->getCreatedAt();
        $update['modified'] = $item->getUpdatedAt();
        $update['stock'] = round($stockItem->getQty());
        $update['availability'] = 1;

        $update['shipping_price_nl'] = '';
        $update['shipping_price_be'] = '';
        $update['delivery_period_nl'] = $config['delivery_in'];
        $update['delivery_period_be'] = $config['delivery_in_be'];

        if ($config['delivery'] == 'attribute') {
            if (!empty($config['delivery_att'])) {
                $update['delivery_period_nl'] = $product->getData($config['delivery_att']);
            }
        }

        if ($config['delivery_be'] == 'attribute') {
            if (!empty($config['delivery_att_be'])) {
                $update['delivery_period_be'] = $product->getData($config['delivery_att_be']);
            }
        }

        if (!$product->getIsSalable() || ($product->getStatus() == 2)) {
            if ($config['delivery'] == 'fixed') {
                $update['delivery_period_nl'] = $config['delivery_out'];
            }

            if ($config['delivery_be'] == 'fixed') {
                $update['delivery_period_be'] = $config['delivery_out_be'];
            }

            $update['availability'] = 0;
        }

        $priceData = Mage::helper('channable')->getProductPrice($product, $config);
        $prices = Mage::getModel('channable/channable')->getPrices($priceData, $confPrices, $product, '', $parentId);
        $calValue = '0.00';

        if (!empty($prices)) {
            $update['price'] = trim($prices['price']);

            if (!empty($prices['special_price'])) {
                $update['discount_price'] = trim($prices['special_price']);
                $calValue = $update['special_price'];
            }

            if (!empty($prices['sales_price'])) {
                $update['discount_price'] = trim($prices['sales_price']);
                $calValue = $update['sales_price'];
            }
        }

        if ($config['shipping_method'] == 'weight') {
            $calValue = $product->getWeight();
        }

        if (!empty($config['shipping_prices'])) {
            foreach ($config['shipping_prices'] as $shippingPrice) {
                if (($calValue >= $shippingPrice['price_from']) && ($calValue <= $shippingPrice['price_to'])) {
                    $shippingCost = $shippingPrice['cost'];
                    $shippingCost = number_format($shippingCost, 2, '.', '');
                    if (empty($shippingPrice['country'])) {
                        $shippingArray['shipping_all'] = $shippingCost;
                    } else {
                        $label = 'shipping_' . strtolower($shippingPrice['country']);
                        $shippingArray[$label] = $shippingCost;
                        if (strtolower($shippingPrice['country']) == 'nl') {
                            $shippingArray['shipping'] = $shippingCost;
                        }
                    }
                }
            }
        }

        if (!empty($shippingArray['shipping_all'])) {
            $update['shipping_price_nl'] = $shippingArray['shipping_all'];
            $update['shipping_price_be'] = $shippingArray['shipping_all'];
        }

        if (!empty($shippingArray['shipping_nl'])) {
            $update['shipping_price_nl'] = $shippingArray['shipping_nl'];
        }

        if (!empty($shippingArray['shipping_be'])) {
            $update['shipping_price_be'] = $shippingArray['shipping_be'];
        }

        if (!empty($config['parent_name']) && !empty($parent)) {
            $update['title'] = $parent->getName();
        }

        return $update;
    }

    /**
     * @param $updates
     * @param $config
     *
     * @return array
     */
    public function postItemUpdate($updates, $config)
    {
        $results = array();
        $headers = array();
        $headers[] = 'X-MAGMODULES-TOKEN: ' . $config['token'];
        $headers[] = 'Content-Type:application/json';

        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $config['webhook']);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($updates));
        curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($request);
        $header = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        if ($header == '200') {
            $results['status'] = 'SUCCESS';
            $results['result'] = $result;
            $results['needs_update'] = 0;
            $results['date'] = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
        } else {
            $results['status'] = 'ERROR';
            $results['result'] = $result;
            $results['needs_update'] = 1;
            $results['date'] = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
        }

        if ($config['debug']) {
            Mage::getSingleton('adminhtml/session')->addNotice('DEBUG: [' . json_encode($updates) . ']');
        }

        if (!empty($config['log'])) {
            $productsIds = array();
            foreach ($updates as $update) {
                $productsIds[] = $update['id'];
            }

            Mage::getModel('channableapi/debug')->addToLog(
                'Item Update',
                'API Call',
                $productsIds,
                json_encode($updates),
                '',
                $results['status']
            );
        }

        return $results;
    }

    /**
     * @param $updates
     * @param $results
     *
     * @return mixed
     */
    public function updateItemRows($updates, $results)
    {
        $i = 0;
        foreach ($updates as $update) {
            $this->load($update['item_id'])
                ->setProductTitle($update['title'])
                ->setPrice($update['price'])
                ->setDiscountPrice($update['discount_price'])
                ->setDeliveryCostNl($update['shipping_price_nl'])
                ->setDeliveryCostBe($update['shipping_price_be'])
                ->setDeliveryTimeNl($update['delivery_period_nl'])
                ->setDeliveryTimeBe($update['delivery_period_be'])
                ->setIsInStock($update['availability'])
                ->setNeedsUpdate($results['needs_update'])
                ->setUpdatedAt(now())
                ->setLastCall(now())
                ->setCallResult($results['status'])
                ->save();
            $i++;
        }

        $results['updates'] = $i;

        return $results;
    }

    /**
     * @param $storeId
     */
    public function cleanItemStore($storeId)
    {
        $table = Mage::getSingleton('core/resource')->getTableName('channable_items');
        $where = 'store_id = ' . $storeId . ' AND created_at < ' . strtotime("-2 days");
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete($table, $where);
    }
}