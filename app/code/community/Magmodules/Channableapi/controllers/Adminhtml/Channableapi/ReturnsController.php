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

class Magmodules_Channableapi_Adminhtml_Channableapi_ReturnsController extends Mage_Adminhtml_Controller_Action
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
        $this->loadLayout()->_setActiveMenu('sales/channableapi')->_addBreadcrumb(
            Mage::helper('channableapi')->__('Channable Return Requests'),
            Mage::helper('channableapi')->__('Channable Return Requests')
        );

        return $this;
    }

    /**
     * @return mixed
     */
    public function processAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var Magmodules_Channableapi_Model_Returns $returnsModel */
        $returnsModel = Mage::getModel('channableapi/returns');

        $data = $this->getRequest()->getParams();
        $result = $returnsModel->processReturn($data);

        if (!empty($result['status']) && $result['status'] == 'success') {
            if (!empty($result['msg'])) {
                $session->addSuccess($result['msg']);
            } else {
                $session->addSuccess($this->__('Return updated'));
            }
        }

        if (!empty($result['status']) && $result['status'] == 'error') {
            if (!empty($result['msg'])) {
                $session->addError($result['msg']);
            } else {
                $session->addError($this->__('Unkown Error'));
            }
        }

        $this->_redirect('*/*/index');
    }

    /**
     * @return mixed
     */
    public function massProcessAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var Magmodules_Channableapi_Model_Returns $returnsModel */
        $returnsModel = Mage::getModel('channableapi/returns');

        $returnIds = $this->getRequest()->getParam('return_ids');
        $type = $this->getRequest()->getParam('type');

        if (!is_array($returnIds)) {
            $session->addError($this->__('Please select item(s)'));
        } else {
            try {
                foreach ($returnIds as $id) {
                    $returnsModel->load($id);
                    if ($returnsModel->getStatus() == 'new') {
                        $returnsModel->setStatus($type)->save();
                    }
                }
                $msg = $this->__('A total of %s record(s) have been updated to %s.', count($returnIds), $type);
                $session->addSuccess($msg);
            } catch (Exception $e) {
                $session->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }

    /**
     * @return mixed
     */
    public function deleteAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var Magmodules_Channableapi_Model_Returns $returnsModel */
        $returnsModel = Mage::getModel('channableapi/returns');

        $data = $this->getRequest()->getParams();
        $result = $returnsModel->deleteReturn($data);

        if (!empty($result['status']) && $result['status'] == 'success') {
            if (!empty($result['msg'])) {
                $session->addSuccess($result['msg']);
            } else {
                $session->addSuccess($this->__('Return deleted'));
            }
        }

        if (!empty($result['status']) && $result['status'] == 'error') {
            if (!empty($result['msg'])) {
                $session->addError($result['msg']);
            } else {
                $session->addError($this->__('Unkown Error'));
            }
        }

        $this->_redirect('*/*/index');
    }

    /**
     * @return mixed
     */
    public function massDeleteAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var Magmodules_Channableapi_Model_Returns $returnsModel */
        $returnsModel = Mage::getModel('channableapi/returns');

        $returnIds = $this->getRequest()->getParam('return_ids');

        if (!is_array($returnIds)) {
            $session->addError($this->__('Please select item(s)'));
        } else {
            try {
                foreach ($returnIds as $id) {
                    $returnsModel->load($id)->delete();
                }
                $msg = $this->__('A total of %s record(s) have been deleted.', count($returnIds));
                $session->addSuccess($msg);
            } catch (Exception $e) {
                $session->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }


    /**
     * @return mixed
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/channableapi/channableapi_returns');
    }
}