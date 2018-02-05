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
        if ($data = $this->reformatFeedData($productData, $storeId, 'add')) {
            $item = $this->setData($data);
            try {
                $item->save();
            } catch (\Exception $e) {
                $this->addToLog('saveItemFeed', $e->getMessage(), 2);
            }
        }
    }

    /**
     * @param $productData
     * @param $storeId
     * @param $type
     *
     * @return array
     */
    public function reformatFeedData($productData, $storeId, $type)
    {
        $data = array();
        $data['item_id'] = $storeId . sprintf('%08d', $productData['id']);
        $data['store_id'] = $storeId;
        $data['product_id'] = $productData['id'];
        $data['title'] = $productData['name'];
        $data['stock'] = (isset($productData['qty']) ? round($productData['qty']) : '');
        $data['parent_id'] = (isset($productData['item_group_id']) ? $productData['item_group_id'] : 0);
        $data['gtin'] = (isset($productData['ean']) ? $productData['ean'] : 0);

        if (isset($row['availability']) && $productData['availability'] == 'in stock') {
            $data['is_in_stock'] = 1;
        } else {
            $data['is_in_stock'] = 1;
        }

        if ($type == 'update') {
            $data['updated'] = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
        }

        if ($type == 'add') {
            $data['created_at'] = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
        }

        if (isset($productData['price'])) {
            if (!empty($productData['price'])) {
                $data['price'] = number_format(preg_replace('/([^0-9\.,])/i', '', $productData['price']), 2);
            } else {
                $data['price'] = '0.00';
            }
            if (!empty($productData['special_price'])) {
                $data['discount_price'] = number_format(preg_replace('/([^0-9\.,])/i', '', $productData['special_price']), 2);
            } else {
                $data['discount_price'] = '';
            }

            return $data;
        }
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

        Mage::helper('channableapi')->addToLog($type, $msg, $level, $force);
    }

    /**
     * @param      $productId
     * @param      $type
     * @param null $reason
     */
    public function invalidateProduct($productId, $type, $reason = null)
    {
        $items = $this->getCollection()
            ->addFieldToFilter(
                array('product_id', 'parent_id'),
                array(array('eq' => $productId), array('eq' => $productId))
            );

        $log = Mage::getStoreConfig('channable_api/debug/log');
        foreach ($items->load() as $item) {
            $item->setNeedsUpdate('1')->setUpdatedAt(now())->save();
            if ($log) {
                $message = 'Scheduled for item update';
                if ($reason) {
                    $message .= ' -' . $reason;
                }
                Mage::getModel('channableapi/debug')->addToLog('Item Update', $type, $productId, $message);
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
            $itemCollection = $this->getCollection()
                ->addFieldToFilter('store_id', $storeId)
                ->addFieldToFilter('needs_update', 1)
                ->setOrder('updated_at', 'ASC')
                ->setPageSize($config['limit'])
                ->setCurPage(1)
                ->load();

            if ($itemCollection->count()) {
                $items = $itemCollection;
            }
        }

        if (!empty($items)) {
            $postData = $this->getProductData($items, $storeId, $config);
            $postResult = $this->postData($postData, $config);
            $this->updateData($postResult);
        } else {
            $date = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
            $postResult = array('status' => 'success', 'qty' => 0, 'date' => $date, 'store_id' => $config['store_id']);
        }

        return $postResult;
    }

    /**
     * @param $storeId
     *
     * @return array
     */
    public function getConfig($storeId)
    {
        $config = Mage::getModel('channable/channable')->getFeedConfig($storeId, 'API');
        $config['webhook'] = Mage::getStoreConfig('channable_api/item/webhook', $storeId);
        $config['limit'] = Mage::getStoreConfig('channable_api/crons/limit', $storeId);
        $config['token'] = Mage::getStoreConfig('channable/connect/token', $storeId);
        $config['debug'] = Mage::getStoreConfig('channable_api/debug/debug');
        $config['log'] = Mage::getStoreConfig('channable_api/debug/log');

        if (empty($config['limit'])) {
            $config['limit'] = 20;
        }

        if ($config['limit'] > 50) {
            $config['limit'] = 50;
        }

        return $config;
    }

    /**
     * @param $items
     * @param $storeId
     * @param $config
     *
     * @return array
     */
    public function getProductData($items, $storeId, $config = array())
    {
        $productData = array();

        /** @var Magmodules_Channable_Model_Channable $feedModel */
        $feedModel = Mage::getModel('channable/channable');

        /** @var Magmodules_Channable_Helper_Data $feedHelper */
        $feedHelper = Mage::helper('channable');

        if (empty($config)) {
            $config = $this->getConfig($storeId);
        }

        $productIds = $this->getProductIds($items);
        if (empty($productIds)) {
            return $productData;
        }

        try {
            /** @var Mage_Core_Model_App_Emulation $appEmulation */
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

            $products = $feedModel->getProducts($config, $productIds)->load();
            $parentRelations = $feedHelper->getParentsFromCollection($products, $config);
            $parents = $feedModel->getParents($parentRelations, $config);
            $prices = $feedHelper->getTypePrices($config, $parents);
            $parentAttributes = $feedHelper->getConfigurableAttributesAsArray($parents, $config);

            foreach ($products as $product) {

                $parent = null;
                $itemId = $storeId . sprintf('%08d', $product->getId());
                if (!empty($parentRelations[$product->getEntityId()])) {
                    foreach ($parentRelations[$product->getEntityId()] as $parentId) {
                        if ($parent = $parents->getItemById($parentId)) {
                            continue;
                        }
                    }
                }

                if ($dataRow = $feedHelper->getProductDataRow($product, $config, $parent, $parentAttributes)) {
                    if ($extraData = $feedModel->getExtraDataFields($dataRow, $config, $product, $prices)) {
                        $dataRow = array_merge($dataRow, $extraData);
                    }
                    $productData[$itemId] = $this->reformatFeedData($dataRow, $storeId, 'update');
                }
            }

            foreach ($items as $item) {
                if (!isset($productData[$item->getItemId()])) {
                    $productData[$item->getItemId()] = array(
                        'item_id'        => $item->getItemId(),
                        'product_id'     => $item->getProductId(),
                        'title'          => $item->getTitle(),
                        'price'          => number_format($item->getPrice(), 2),
                        'discount_price' => number_format($item->getDiscountPrice(), 2),
                        'gtin'           => $item->getGtin(),
                        'parent_id'      => $item->getParentId(),
                        'stock'          => 0,
                        'availability'   => 0,
                        'updated_at'     => Mage::getSingleton('core/date')->gmtDate('Y-m-d H:i:s'),
                    );
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        } catch (\Exception $e) {
            $this->addToLog('getProductData', $e->getMessage(), 2);
        }

        return array_values($productData);
    }

    /**
     * @param $items
     *
     * @return array
     */
    public function getProductIds($items)
    {
        $productIds = array();
        foreach ($items as $item) {
            $productIds[] = $item->getProductId();
        }

        return $productIds;
    }

    /**
     * @param $postData
     * @param $config
     *
     * @return array
     */
    public function postData($postData, $config)
    {
        $results = array();
        $httpHeader = array('X-MAGMODULES-TOKEN: ' . $config['token'], 'Content-Type:application/json');

        $post = array();
        $skip = $this->getNonApiFields();
        foreach ($postData as $id => $prod) {
            foreach ($prod as $k => $v) {
                if (in_array($k, $skip)) {
                    continue;
                }
                if ($k == 'product_id') {
                    $k = 'id';
                }
                $post[$id][$k] = $v;
            }
        }

        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $config['webhook']);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($request, CURLOPT_POSTFIELDS, json_encode(array_values($post)));
        curl_setopt($request, CURLOPT_HTTPHEADER, $httpHeader);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($request);
        $header = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        if ($header == '200') {
            $results['status'] = 'success';
            $results['needs_update'] = 0;
        } else {
            $results['status'] = 'error';
            $results['needs_update'] = 1;
        }

        $results['result'] = json_decode($result, true);
        $results['qty'] = count($postData);
        $results['date'] = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
        $results['store_id'] = $config['store_id'];
        $results['webhook'] = $config['webhook'];
        $results['post_data'] = $postData;

        if ($config['debug']) {
            Mage::getSingleton('adminhtml/session')->addNotice('DEBUG: [' . json_encode($post) . ']');
        }

        if (!empty($config['log'])) {
            $productsIds = array();
            foreach ($postData as $update) {
                $productsIds[] = $update['product_id'];
            }

            Mage::getModel('channableapi/debug')->addToLog(
                'Item Update',
                'API Call',
                $productsIds,
                json_encode($postData),
                '',
                $results['status']
            );
        }

        return $results;
    }

    /**
     * @return array
     */
    public function getNonApiFields()
    {
        return array('item_id', 'availability', 'parent_id', 'updated_at', 'title', 'store_id');
    }

    /**
     * @param $postResult
     */
    public function updateData($postResult)
    {
        $itemsResult = $postResult['result'];
        $postData = $postResult['post_data'];
        $items = isset($itemsResult['content']) ? $itemsResult['content'] : array();
        $status = isset($postResult['status']) ? $postResult['status'] : '';

        if ($status == 'success') {
            foreach ($items as $item) {
                $key = array_search($item['id'], array_column($postData, 'product_id'));
                $postData[$key]['call_result'] = $item['message'];
                $postData[$key]['status'] = ucfirst($item['status']);
                $postData[$key]['needs_update'] = ($item['status'] == 'success') ? 0 : 1;
                $postData[$key]['last_call'] = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
                $postData[$key]['updated_at'] = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
                $postData[$key]['attempts'] = 1;
                if ($postData[$key]['needs_update']) {
                    $oldItem = $this->load($postData[$key]['item_id']);
                    if ($oldItem->getStatus() == 'Error') {
                        $postData[$key]['attempts'] = $oldItem->getAttempts() + 1;
                        if ($postData[$key]['attempts'] > 3) {
                            $postData[$key]['status'] = 'Not Found';
                            $postData[$key]['needs_update'] = 0;
                        }
                    }
                }
            }
        }

        foreach ($postData as $key => $data) {
            try {
                $this->setData($data)->save();
            } catch (\Exception $e) {
                $this->addToLog('updateData', $e->getMessage(), 2);
            }
        }
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