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

class Magmodules_Channableapi_Model_Debug extends Mage_Core_Model_Abstract
{

    /**
     * Construct
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('channableapi/debug');
    }

    /**
     * @param        $type
     * @param        $action
     * @param        $productsIds
     * @param        $message
     * @param string $parent
     * @param string $status
     */
    public function addToLog($type, $action, $productsIds, $message, $parent = '', $status = '')
    {
        if (!$this->checkTableExists()) {
            return;
        }

        if ($parent) {
            $message .= ' ' . $parent;
        }

        if (is_array($productsIds)) {
            $productsIds = implode(',', $productsIds);
        }

        $this->setType($type)
            ->setAction($action)
            ->setMessage($message)
            ->setIds($productsIds)
            ->setStatus($status)
            ->setCreatedTime(now())
            ->save();
    }

    /**
     * @param $id
     */
    public function orderSuccess($id)
    {
        if (!$this->checkTableExists()) {
            return;
        }

        $this->setType('Order')
            ->setIds($id)
            ->setStatus('Success')
            ->setAction('Post')
            ->setCreatedTime(now())
            ->save();
    }

    /**
     * @param $errors
     * @param $channableId
     */
    public function orderError($errors, $channableId)
    {
        if (!$this->checkTableExists()) {
            return;
        }

        if (is_array($errors)) {
            $errors = implode(',', $errors);
        }

        $this->setType('Order')
            ->setIds($channableId)
            ->setStatus('Error')
            ->setAction('Post')
            ->setMessage($errors)
            ->setCreatedTime(now())
            ->save();
    }

    /**
     * @return bool
     */
    public function checkTableExists()
    {
        $itemTable = Mage::getSingleton('core/resource')->getTableName('channable_debug');
        $exists = (boolean) Mage::getSingleton('core/resource')->getConnection('core_write')->showTableStatus(
            $itemTable
        );

        return $exists;
    }
}
