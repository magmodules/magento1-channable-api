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
        $request = $this->getRequest();
        $storeId = $request->getParam('store');
        $response = $helper->validateRequestData($request);

        if (empty($response)) {
            $data = file_get_contents('php://input');
            $orderData = $helper->validateJsonOrderData($data, $request);
            if (isset($orderData['errors'])) {
                $response = $orderData;
            }
        }

        if (empty($response)) {
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
            try {
                $response = Mage::getModel('channableapi/order')->importOrder($orderData, $storeId);
            } catch (Exception $e) {
                $response = $helper->jsonResponse($e->getMessage());
            }
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }

        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-type', 'application/json', true)
            ->setHeader('Cache-control', 'no-cache', true)
            ->setBody(json_encode($response));
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
            $this->getResponse()
                ->clearHeaders()
                ->setHeader('Content-type', 'application/json', true)
                ->setHeader('Cache-control', 'no-cache', true)
                ->setBody(json_encode($response));
        }
    }
}
