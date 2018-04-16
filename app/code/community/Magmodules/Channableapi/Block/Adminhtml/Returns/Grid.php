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

class Magmodules_Channableapi_Block_Adminhtml_Returns_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    /**
     * Magmodules_Channableapi_Block_Adminhtml_Items_Grid constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('returnsGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * @param $row
     *
     * @return string
     */
    public function getRowUrl($row)
    {
        return '';
    }

    /**
     * @return mixed
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('channableapi/returns')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function _prepareColumns()
    {
        /** @var Magmodules_Channableapi_Helper_Data $helper */
        $helper = Mage::helper('channableapi');

        $this->addColumn(
            'id', array(
                'header' => $helper->__('ID'),
                'width'  => '50px',
                'index'  => 'id',
                'type'   => 'number',
            )
        );

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'store_id', array(
                    'header'     => $helper->__('Store'),
                    'index'      => 'store_id',
                    'type'       => 'store',
                    'width'      => '140px',
                    'store_view' => true,
                )
            );
        }

        $this->addColumn(
            'order_id', array(
                'header' => $helper->__('Order ID'),
                'index'  => 'order_id',
            )
        );

        $this->addColumn(
            'magento_increment_id', array(
                'header'   => $helper->__('Magento Order ID'),
                'index'    => 'magento_increment_id',
                'renderer' => 'channableapi/adminhtml_returns_renderer_orderlink',
            )
        );

        $this->addColumn(
            'customer_name', array(
                'header' => $helper->__('Customer'),
                'index'  => 'customer_name',
            )
        );

        $this->addColumn(
            'item', array(
                'header'   => $helper->__('Item'),
                'index'    => 'item',
                'renderer' => 'channableapi/adminhtml_returns_renderer_item',
            )
        );

        $this->addColumn(
            'reason', array(
                'header'   => $helper->__('Reason'),
                'index'    => 'reason',
                'renderer' => 'channableapi/adminhtml_returns_renderer_reason',
            )
        );

        $this->addColumn(
            'created_at', array(
                'header' => $helper->__('Imported At'),
                'type'   => 'datetime',
                'index'  => 'created_at',
            )
        );

        $this->addColumn(
            'status', array(
                'header'  => $helper->__('Status'),
                'index'   => 'status',
                'type'    => 'options',
                'options' => Mage::getModel('channableapi/returns_status')->getOptionArray()
            )
        );

        $this->addColumn(
            'action',
            array(
                'header'   => $helper->__('Action'),
                'align'    => 'left',
                'index'    => 'action',
                'filter'   => false,
                'sortable' => false,
                'renderer' => 'channableapi/adminhtml_returns_renderer_action',
            )
        );

        return parent::_prepareColumns();
    }

    /**
     * @return $this
     */
    protected function _prepareMassaction()
    {
        /** @var Magmodules_Channableapi_Helper_Data $helper */
        $helper = Mage::helper('channableapi');

        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('return_ids');

        $this->getMassactionBlock()->addItem(
            'accepted', array(
                'label'   => $helper->__('Accept'),
                'url'     => $this->getUrl('*/*/massProcess', array('type' => 'accepted')),
                'confirm' => $helper->__('Are you sure you want to update these as "Accepted" and close these returns? This action can not be undone!')
            )
        );

        $this->getMassactionBlock()->addItem(
            'rejected', array(
                'label'   => $helper->__('Reject'),
                'url'     => $this->getUrl('*/*/massProcess', array('type' => 'rejected')),
                'confirm' => $helper->__('Are you sure you want to update these as "Rejected" and close these returns? This action can not be undone!')
            )
        );

        $this->getMassactionBlock()->addItem(
            'repaired', array(
                'label'   => $helper->__('Repair'),
                'url'     => $this->getUrl('*/*/massProcess', array('type' => 'repaired')),
                'confirm' => $helper->__('Are you sure you want to update these as "Repaired" and close these returns? This action can not be undone!')
            )
        );

        $this->getMassactionBlock()->addItem(
            'exchanged', array(
                'label'   => $helper->__('Exchange'),
                'url'     => $this->getUrl('*/*/massProcess', array('type' => 'exchanged')),
                'confirm' => $helper->__('Are you sure you want to update these as "Exchanged" and close these returns? This action can not be undone!')
            )
        );

        $this->getMassactionBlock()->addItem(
            'keeps', array(
                'label'   => $helper->__('Keep'),
                'url'     => $this->getUrl('*/*/massProcess', array('type' => 'keeps')),
                'confirm' => $helper->__('Are you sure you want to update these as "Keeps" and close these returns? This action can not be undone!')
            )
        );

        $this->getMassactionBlock()->addItem(
            'cancelled', array(
                'label'   => $helper->__('Cancel'),
                'url'     => $this->getUrl('*/*/massProcess', array('type' => 'cancelled')),
                'confirm' => $helper->__('Are you sure you want to update these as "Cancelled" and close these returns? This action can not be undone!')
            )
        );

        return $this;
    }

}