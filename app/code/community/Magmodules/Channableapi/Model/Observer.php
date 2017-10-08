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

class Magmodules_Channableapi_Model_Observer
{

    /**
     * @return $this
     */
    public function shopitemApi()
    {
        $enabled = Mage::getStoreConfig('channable_api/general/enabled');
        if (!$enabled) {
            return $this;
        }

        try {
            $stores = Mage::helper('channableapi')->getEnabledItemStores();
            foreach ($stores as $storeId) {
                $timeStart = microtime(true);
                $appEmulation = Mage::getSingleton('core/app_emulation');
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                if ($result = Mage::getModel('channableapi/items')->runUpdate($storeId)) {
                    if ($result['status'] == 'SUCCESS') {
                        $html = $result['status'] . ' - Updates: ' . $result['updates'] . '<br/>';
                        $html .= '<small>Date: ' . $result['date'] . ' - ';
                        $html .= 'Time: ' . number_format((microtime(true) - $timeStart), 4) . '</small>';
                    } else {
                        $html = $result['status'] . '<br/><small>Date: ' . $result['date'] . ' - ';
                        $html .= 'Time: ' . number_format((microtime(true) - $timeStart), 4) . '</small>';
                    }

                    $config = new Mage_Core_Model_Config();
                    $config->saveConfig('channable_api/item/result', $html, 'stores', $storeId);
                }

                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
            }
        } catch (Exception $e) {
            Mage::log('Channable API shopitemApi:' . $e->getMessage());
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function catalog_product_save_before(Varien_Event_Observer $observer)
    {
        try {
            $product = $observer->getProduct();
            if ($product->hasDataChanges()) {
                $storeId = $product->getStoreId();
                if ($reason = $this->_compareProduct($product->getData(), $product->getOrigData())) {
                    $enabled = Mage::getStoreConfig('channable_api/general/enabled');
                    if ($enabled && ($storeId != 0)) {
                        $enabled = Mage::getStoreConfig('channable_api/item/enabled', $storeId);
                    }

                    if ($enabled) {
                        if ($this->checkTableExists()) {
                            $parentIds = array();
                            if ($product->getTypeId() == 'simple') {
                                $configIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild(
                                    $product->getId()
                                );
                                $groupedIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild(
                                    $product->getId()
                                );
                                $parentReason = 'Simple ID: ' . $product->getId();
                            } else {
                                $configIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds(
                                    $product->getId()
                                );
                                $groupedIds = Mage::getModel('catalog/product_type_grouped')->getChildrenIds(
                                    $product->getId()
                                );
                                $parentReason = 'Parent ID: ' . $product->getId();
                            }

                            if (!empty($configIds)) {
                                if (isset($configIds[0])) {
                                    if (is_array($configIds[0])) {
                                        $parentIds = array_merge($configIds[0], $parentIds);
                                    } else {
                                        $parentIds[] = $configIds[0];
                                    }
                                }
                            }

                            if (!empty($groupedIds)) {
                                if (isset($groupedIds[0])) {
                                    if (is_array($groupedIds[0])) {
                                        $parentIds = array_merge($groupedIds[0], $parentIds);
                                    } else {
                                        $parentIds[] = $groupedIds[0];
                                    }
                                }
                            }

                            $type = 'Product Edit';
                            if (!empty($parentIds)) {
                                foreach ($parentIds as $id) {
                                    Mage::getModel('channableapi/items')->invalidateProduct(
                                        $id, $type, $reason,
                                        $parentReason, $product->getStoreId()
                                    );
                                }
                            }

                            Mage::getModel('channableapi/items')->invalidateProduct(
                                $product->getId(), $type, $reason, '',
                                $product->getStoreId()
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Mage::log('Channable API catalog_product_save_before:' . $e->getMessage());
        }
    }

    /**
     * @param $new
     * @param $old
     *
     * @return string
     */
    protected function _compareProduct($new, $old)
    {
        if (isset($old)) {
            if (!empty($old['price']) && !empty($new['price'])) {
                if ($old['price'] != $new['price']) {
                    return 'Price Change';
                }
            }

            if (!empty($old['special_price']) && !empty($new['special_price'])) {
                if ($old['special_price'] != $new['special_price']) {
                    return 'Special Price Change';
                }
            }

            if ($old['status'] != $new['status']) {
                return 'Status Change';
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function checkTableExists()
    {
        $itemTable = Mage::getSingleton('core/resource')->getTableName('channable_items');
        $exists = (boolean)Mage::getSingleton('core/resource')->getConnection('core_write')->showTableStatus(
            $itemTable
        );

        return $exists;
    }

    /**
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function cataloginventory_stock_item_save_after(Varien_Event_Observer $observer)
    {
        $enabled = Mage::getStoreConfig('channable_api/general/enabled');
        if (!$enabled) {
            return $this;
        }

        try {
            if ($this->checkTableExists()) {
                $item = $observer->getEvent()->getItem();
                if ($item->getStockStatusChangedAuto() || ($item->getQtyCorrection() != 0)) {
                    $parentIds = array();
                    $type = 'Inventory Change';
                    if ($item->getTypeId() == 'simple') {
                        $configIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild(
                            $item->getProductId()
                        );
                        $groupedIds = Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild(
                            $item->getProductId()
                        );
                        $parentReason = 'Simple ID: ' . $item->getProductId();
                    } else {
                        $configIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds(
                            $item->getProductId()
                        );
                        $groupedIds = Mage::getModel('catalog/product_type_grouped')->getChildrenIds(
                            $item->getProductId()
                        );
                        $parentReason = 'Parent ID: ' . $item->getProductId();
                    }

                    if (!empty($configIds)) {
                        if (isset($configIds[0])) {
                            if (is_array($configIds[0])) {
                                $parentIds = array_merge($configIds[0], $parentIds);
                            } else {
                                $parentIds[] = $configIds[0];
                            }
                        }
                    }

                    if (!empty($groupedIds)) {
                        if (isset($groupedIds[0])) {
                            if (is_array($groupedIds[0])) {
                                $parentIds = array_merge($groupedIds[0], $parentIds);
                            } else {
                                $parentIds[] = $groupedIds[0];
                            }
                        }
                    }

                    $reason = '';

                    if ($item->getStockStatusChangedAuto()) {
                        $reason = 'Stock Status Change';
                    }

                    if ($item->getQtyCorrection() != 0) {
                        if ($item->getQtyCorrection() > 0) {
                            $change = '+' . $item->getQtyCorrection();
                        } else {
                            $change = $item->getQtyCorrection();
                        }

                        $reason = 'Stock Change (' . $change . ')';
                    }

                    if ($parentIds) {
                        foreach ($parentIds as $id) {
                            Mage::getModel('channableapi/items')->invalidateProduct(
                                $id,
                                $type,
                                $reason,
                                $parentReason
                            );
                        }
                    }

                    Mage::getModel('channableapi/items')->invalidateProduct($item->getProductId(), $type, $reason);
                }
            }
        } catch (Exception $e) {
            Mage::log('Channable API cataloginventory_stock_item_save_after:' . $e->getMessage());
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function sales_model_service_quote_submit_before(Varien_Event_Observer $observer)
    {
        $enabled = Mage::getStoreConfig('channable_api/general/enabled');
        if ($enabled) {
            return $this;
        }

        try {
            if ($this->checkTableExists()) {
                $quote = $observer->getEvent()->getQuote();
                $type = 'Sales Order';
                $reason = 'Item Ordered';
                foreach ($quote->getAllItems() as $item) {
                    Mage::getModel('channableapi/items')->invalidateProduct($item->getProductId(), $type, $reason);
                }
            }
        } catch (Exception $e) {
            Mage::log('Channable API sales_model_service_quote_submit_before:' . $e->getMessage());
        }
    }

    /**
     * Clean Old Item Entries
     */
    public function cleanItems()
    {
        $enabled = Mage::getStoreConfig('channable_api/general/enabled');
        if ($enabled) {
            return $this;
        }

        try {
            $stores = Mage::helper('channableapi')->getEnabledItemStores();
            if ($stores && $this->checkTableExists()) {
                $storeIds = implode(',', $stores);
                $items = Mage::getSingleton('core/resource')->getTableName('channable_items');
                $where = 'store_id NOT IN (' . $storeIds . ')';
                Mage::getSingleton('core/resource')->getConnection('core_read')->delete($items, $where);
            }

            $log = Mage::getStoreConfig('channable_api/debug/log');
            if ($log) {
                $debug = Mage::getSingleton('core/resource')->getTableName('channable_debug');
                $where = 'datediff(now(), created_time) > 5';
                Mage::getSingleton('core/resource')->getConnection('core_read')->delete($debug, $where);
            }
        } catch (Exception $e) {
            Mage::log('Channable API cleanItems:' . $e->getMessage());
        }
    }

}