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

class Magmodules_Channableapi_OrderController extends Mage_Core_Controller_Front_Action
{

    /**
     * Index Action
     */
    public function indexAction()
    {
        $helper = Mage::helper('channableapi');

        $storeId = $this->getRequest()->getParam('store');
        if ($storeId != Mage::app()->getStore()->getStoreId()) {
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
        }

        $response = $helper->checkIpRestriction();

        if (empty($response)) {
            $enabled = Mage::getStoreConfig('channable_api/general/enabled', $storeId);
            $order = Mage::getStoreConfig('channable_api/order/enabled', $storeId);
            $token = Mage::getStoreConfig('channable/connect/token', $storeId);
            $code = $this->getRequest()->getParam('code');
            $test = $this->getRequest()->getParam('test');

            if ($enabled && $order && $token && $code) {
                if ($code == $token) {
                    if (!empty($test)) {
                        $data = $helper->getTestJsonData($test);
                    } else {
                        $data = file_get_contents('php://input');
                    }

                    if (!empty($data)) {
                        if ($data = $helper->validateJsonOrderData($data)) {
                            $response = Mage::getModel('channableapi/order')->importOrder($data, $storeId);
                        } else {
                            $response = $helper->jsonResponse('No validated data');
                        }
                    } else {
                        $response = $helper->jsonResponse('Missing Data');
                    }
                } else {
                    $response = $helper->jsonResponse('Unknown Token');
                }
            } else {
                $response = $helper->jsonResponse('Not enabled');
            }
        }

        if (!empty($response)) {
            $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody(json_encode($response));
        }

        if ($storeId != Mage::app()->getStore()->getStoreId()) {
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }

    /**
     * Get Action
     */
    public function getAction()
    {
        $helper = Mage::helper('channableapi');
        $enabled = Mage::getStoreConfig('channable_api/general/enabled');
        $token = Mage::getStoreConfig('channable/connect/token');
        $code = $this->getRequest()->getParam('code');
        if ($enabled && $token && $code) {
            if ($code == $token) {
                if ($id = $this->getRequest()->getParam('id')) {
                    $response = Mage::getModel('channableapi/order')->getOrderById($id);
                } else {
                    $response = $helper->jsonResponse('Missing ID');
                }
            } else {
                $response = $helper->jsonResponse('Unknown Token');
            }
        } else {
            $response = $helper->jsonResponse('Extension not enabled!');
        }

        if (!empty($response)) {
            $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody(json_encode($response));
        }
    }
}
