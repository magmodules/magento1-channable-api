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

class Magmodules_Channableapi_Model_Returns extends Mage_Core_Model_Abstract
{


    /**
     * @var Magmodules_Channableapi_Helper_Data
     */
    private $helper;

    /**
     * Construct
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('channableapi/returns');
        $this->helper = Mage::helper('channableapi');
    }

    /**
     * @param $returnData
     * @param $storeId
     *
     * @return array
     */
    public function importReturn($returnData, $storeId)
    {
        $response = array();
        $item = $returnData['item'];
        $customer = $returnData['customer'];
        $address = $returnData['address'];

        $data = [
            'store_id'      => $storeId,
            'order_id'      => $item['order_id'],
            'channel_name'  => $returnData['channel_name'],
            'channel_id'    => $returnData['channel_id'],
            'channable_id'  => $returnData['channable_id'],
            'customer_name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'item'          => json_encode($item),
            'customer'      => json_encode($customer),
            'address'       => json_encode($address),
            'status'        => $returnData['status'],
            'reason'        => $item['reason'],
            'comment'       => $item['comment']
        ];

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($item['order_id'], 'channable_id');
        if ($order->getId() > 0) {
            $data['magento_order_id'] = $order->getId();
            $data['magento_increment_id'] = $order->getIncrementId();
        }

        /** @var Magmodules_Channableapi_Model_Returns $retun */
        $returns = $this->setData($data)->setCreatedAt(now());

        try {
            $returns->save();
            $response['validated'] = 'true';
            $response['return_id'] = $returns->getId();
        } catch (\Exception $e) {
            $response['validated'] = 'false';
            $response['errors'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function processReturn($params)
    {
        $result = array();

        if (empty($params['id'])) {
            $result['status'] = 'error';
            $result['msg'] = $this->helper->__('Id missing');
            return $result;
        }

        if (empty($params['type'])) {
            $result['status'] = 'error';
            $result['msg'] = $this->helper->__('Type missing');
            return $result;
        }
        /** @var Magmodules_Channableapi_Model_Returns $return */
        $return = $this->load($params['id']);
        if ($return->getId() < 1) {
            $result['status'] = 'error';
            $result['msg'] = $this->helper->__('Return with id %s not found', $params['id']);
            return $result;
        }

        try {
            $return->setStatus($params['type'])->save();
            $result['status'] = 'success';
            $result['msg'] = $this->helper->__('Return processed, new status: %s', $params['type']);
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['msg'] = $e->getMessage();
        }

      return $result;
    }

    /**
     * @param $params
     *
     * @return array
     */
    public function deleteReturn($params)
    {
        $result = array();

        if (empty($params['id'])) {
            $result['status'] = 'error';
            $result['msg'] = $this->helper->__('Id missing');
            return $result;
        }

        /** @var Magmodules_Channableapi_Model_Returns $return */
        $return = $this->load($params['id']);
        if ($return->getId() < 1) {
            $result['status'] = 'error';
            $result['msg'] = $this->helper->__('Return with id %s not found', $params['id']);
            return $result;
        }

        try {
            $return->setStatus($params['type'])->save();
            $result['status'] = 'success';
            $result['msg'] = $this->helper->__('Return deleted');
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['msg'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function getReturnStatus($id)
    {
        $result = array();

        /** @var Magmodules_Channableapi_Model_Returns $return */
        $return = $this->load($id);

        if ($return->getId() < 1) {
            $result['validated'] = 'false';
            $result['errors'] = $this->helper->__('Return with id %s not found', $id);
            return $result;
        }

        $result['validated'] = 'true';
        $result['return_id'] = $return->getId();
        $result['status'] = $return->getStatus();
        return $result;
    }



}