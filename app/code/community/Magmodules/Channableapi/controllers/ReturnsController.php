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

class Magmodules_Channableapi_ReturnsController extends Mage_Core_Controller_Front_Action
{

    /**
     * @var Magmodules_Channableapi_Helper_Data
     */
    public $helper;

    /**
     * @var Magmodules_Channableapi_Model_Returns
     */
    public $returnsModel;

    /**
     * Magmodules_Channableapi_ReturnsController constructor.
     */
    public function _construct()
    {
        $this->helper = Mage::helper('channableapi');
        $this->returnsModel = Mage::getModel('channableapi/returns');
        parent::_construct();
    }

    /**
     * Index Action
     */
    public function indexAction()
    {
        $returnData = null;
        $request = $this->getRequest();
        $storeId = $request->getParam('store');
        $response = $this->helper->validateRequestData($request, 'returns');

        if (empty($response['errors'])) {
            $data = file_get_contents('php://input');
            $returnData = $this->helper->validateJsonReturnData($data, $request);
            if (!empty($returnData['errors'])) {
                $response = $returnData;
            }
        }

        if (empty($response['errors'])) {
            /** @var Mage_Core_Model_App_Emulation $appEmulation */
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
            try {
                $response = $this->returnsModel->importReturn($returnData, $storeId);
            } catch (Exception $e) {
                $response = $this->helper->jsonResponse($e->getMessage());
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
        $enabled = $this->helper->getEnabled();
        $token = $this->helper->getToken();
        $code = $this->getRequest()->getParam('code');

        if ($enabled && $token && $code) {
            if ($code == $token) {
                if ($id = $this->getRequest()->getParam('id')) {
                    $response = $this->returnsModel->getReturnStatus($id);
                } else {
                    $response = $this->helper->jsonResponse('Missing ID');
                }
            } else {
                $response = $this->helper->jsonResponse('Unknown Token');
            }
        } else {
            $response = $this->helper->jsonResponse('Extension not enabled!');
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
