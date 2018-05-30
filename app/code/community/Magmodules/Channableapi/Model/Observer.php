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
            /** @var Magmodules_Channableapi_Model_Items $itemModel */
            $itemModel = Mage::getModel('channableapi/items');
            $stores = Mage::helper('channableapi')->getEnabledItemStores();
            foreach ($stores as $storeId) {
                $timeStart = microtime(true);
                /** @var Mage_Core_Model_App_Emulation $appEmulation */
                $appEmulation = Mage::getSingleton('core/app_emulation');
                $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                if ($result = $itemModel->runUpdate($storeId)) {
                    if ($result['status'] == 'success') {
                        $html = sprintf(
                            '%s - Updates: %s <br/><small>Date: %s - Time %s</small>',
                            $result['status'],
                            $result['updates'],
                            $result['date'],
                            number_format((microtime(true) - $timeStart))
                        );

                    } else {
                        $html = sprintf(
                            '%s<br/><small>Date: %s - Time %s</small>',
                            $result['status'],
                            $result['date'],
                            number_format((microtime(true) - $timeStart))
                        );
                    }
                    /** @var Mage_Core_Model_Config $config */
                    $config = Mage::getModel('core/config');
                    $config->saveConfig('channable_api/item/result', $html, 'stores', $storeId);
                }
                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
            }
        } catch (\Exception $e) {
            $itemModel->addTolog('catalog_product_save_before', $e->getMessage(), 2);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function catalog_product_save_before(Varien_Event_Observer $observer)
    {
        $enabled = Mage::getStoreConfig('channable_api/general/enabled');
        if (!$enabled) {
            return $this;
        }

        /** @var Magmodules_Channableapi_Model_Items $itemModel */
        $itemModel = Mage::getModel('channableapi/items');
        $type = 'Product Edit';

        try {
            /** @var Mage_Catalog_Model_Product $product */
            $product = $observer->getProduct();
            if ($product->hasDataChanges()) {
                $itemModel->invalidateProduct($product->getId(), $type);
            }
        } catch (\Exception $e) {
            $itemModel->addTolog('catalog_product_save_before', $e->getMessage(), 2);
        }

        return $this;
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

        /** @var Magmodules_Channableapi_Model_Items $itemModel */
        $itemModel = Mage::getModel('channableapi/items');
        $type = 'Inventory Change';

        try {
            $item = $observer->getEvent()->getItem();
            if ($item->getStockStatusChangedAuto() || ($item->getQtyCorrection() != 0)) {
                $itemModel->invalidateProduct($item->getProductId(), $type);
            }
        } catch (\Exception $e) {
            $itemModel->addTolog('cataloginventory_stock_item_save_after', $e->getMessage(), 2);
        }

        return $this;
    }

    /**
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function sales_model_service_quote_submit_before(Varien_Event_Observer $observer)
    {
        $enabled = Mage::getStoreConfig('channable_api/general/enabled');
        if (!$enabled) {
            return $this;
        }

        /** @var Magmodules_Channableapi_Model_Items $itemModel */
        $itemModel = Mage::getModel('channableapi/items');

        try {
            $type = 'Sales Order';
            $reason = 'Item Ordered';
            $quote = $observer->getEvent()->getQuote();
            foreach ($quote->getAllItems() as $item) {
                $itemModel->invalidateProduct($item->getProductId(), $type, $reason);
            }
        } catch (\Exception $e) {
            $itemModel->addTolog('sales_model_service_quote_submit_before', $e->getMessage(), 2);
        }

        return $this;
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

        /** @var Magmodules_Channableapi_Model_Items $itemModel */
        $itemModel = Mage::getModel('channableapi/items');

        try {
            $stores = Mage::helper('channableapi')->getEnabledItemStores();
            if ($stores) {
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
            $itemModel->addTolog('cleanItems', $e->getMessage(), 2);
        }
    }

}