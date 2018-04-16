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

class Magmodules_Channableapi_Block_Adminhtml_Returns_Renderer_Action
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{

    /**
     * @param Varien_Object $row
     *
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $this->getColumn()->setActions($this->getActions($row));
        return parent::render($row);
    }

    /**
     * @param $row
     *
     * @return array
     */
    public function getActions($row)
    {
        /** @var Magmodules_Channableapi_Helper_Data $helper */
        $helper = Mage::helper('channableapi');

        $actions = array();

        if ($row->getStatus() == 'new') {
            $actions[] = array(
                'caption' => $helper->__('Accept'),
                'url'     => $helper->getBackendUrl('*/*/process', array('id' => $row->getId(), 'type' => 'accepted')),
                'confirm' => $helper->__('Are you sure you want to update this as Accepted and close this return? This action can not be undone!'),
            );

            $actions[] = array(
                'caption' => $helper->__('Reject'),
                'url'     => $helper->getBackendUrl('*/*/process', array('id' => $row->getId(), 'type' => 'rejected')),
                'confirm' => $helper->__('Are you sure you want to update this as Rejected and close this return? This action can not be undone!'),
            );

            $actions[] = array(
                'caption' => $helper->__('Repair'),
                'url'     => $helper->getBackendUrl('*/*/process', array('id' => $row->getId(), 'type' => 'repaired')),
                'confirm' => $helper->__('Are you sure you want to update this as Repaired and close this return? This action can not be undone!'),
            );

            $actions[] = array(
                'caption' => $helper->__('Exchange'),
                'url'     => $helper->getBackendUrl('*/*/process', array('id' => $row->getId(), 'type' => 'exchanged')),
                'confirm' => $helper->__('Are you sure you want to update this as Exchanged and close this return? This action can not be undone!'),
            );

            $actions[] = array(
                'caption' => $helper->__('Keep'),
                'url'     => $helper->getBackendUrl('*/*/process', array('id' => $row->getId(), 'type' => 'keeps')),
                'confirm' => $helper->__('Are you sure you want to update this as Keeps and close this return? This action can not be undone!'),
            );

            $actions[] = array(
                'caption' => $helper->__('Cancel'),
                'url'     => $helper->getBackendUrl('*/*/process', array('id' => $row->getId(), 'type' => 'cancelled')),
                'confirm' => $helper->__('Are you sure you want to update this as Cancelled and close this return? This action can not be undone!'),
            );
        } else {
            $actions[] = array(
                'caption' => $helper->__('Delete'),
                'url'     => $helper->getBackendUrl('*/*/delete', array('id' => $row->getId())),
                'confirm' => $helper->__('Are you sure you want to delete this return? This action can not be undone and will not update the status on Channable!'),
            );
        }

        return $actions;
    }

}