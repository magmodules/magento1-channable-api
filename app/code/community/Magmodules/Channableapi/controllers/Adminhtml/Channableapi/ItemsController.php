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

class Magmodules_Channableapi_Adminhtml_Channableapi_ItemsController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Index Action
     */
    public function indexAction()
    {
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()->_setActiveMenu('catalog/channableapi')->_addBreadcrumb(
            Mage::helper('channableapi')->__('Channable Items'),
            Mage::helper('channableapi')->__('Channable Items')
        );

        return $this;
    }

    /**
     * Update Item Action
     */
    public function updateItemAction()
    {
        if (Mage::getStoreConfig('channable_api/general/enabled')) {
            $itemId = $this->getRequest()->getParam('item_id');
            if (!empty($itemId)) {
                $item = Mage::getModel('channableapi/items')->load($itemId);
                $result = Mage::getModel('channableapi/items')->runUpdate($item->getStoreId(), array($item));
                if ($result['status'] == 'SUCCESS') {
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('channableapi')->__('Item updated')
                    );
                } else {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('channableapi')->__('Could not update item')
                    );
                }
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('channableapi')->__('Please enable the extension first')
            );
        }

        $this->_redirect('*/*/index');
    }

    /**
     * Mass Invalidate Action
     */
    public function massInvalidateAction()
    {
        $ids = $this->getRequest()->getParam('item_ids');
        if (!is_array($ids)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('channableapi')->__('Please select item(s)')
            );
        } else {
            try {
                foreach ($ids as $entityId) {
                    Mage::getModel('channableapi/items')->load($entityId)
                        ->setNeedsUpdate(1)
                        ->setCallResult()
                        ->save();
                }

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('channableapi')->__(
                        'Total of %d record(s) were updated!',
                        count($ids)
                    )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    /**
     * @return mixed
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/catalog/channableapi_items');
    }

}