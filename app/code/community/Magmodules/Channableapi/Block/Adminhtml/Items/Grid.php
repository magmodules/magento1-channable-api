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

class Magmodules_Channableapi_Block_Adminhtml_Items_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    /**
     * Magmodules_Channableapi_Block_Adminhtml_Items_Grid constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('itemsGrid');
        $this->setDefaultSort('last_call');
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
        $storeIds = Mage::helper('channableapi')->getEnabledItemStores();
        $collection = Mage::getModel('channableapi/items')->getCollection();
        $collection->addFieldToFilter('store_id', array('in' => $storeIds));
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return mixed
     */
    protected function _prepareColumns()
    {

        $store = $this->_getStore();
        $this->addColumn(
            'product_id', array(
                'header' => Mage::helper('channableapi')->__('Id'),
                'width'  => '50px',
                'index'  => 'product_id',
                'type'   => 'number',
            )
        );

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'store_id', array(
                    'header'     => Mage::helper('channableapi')->__('Store'),
                    'index'      => 'store_id',
                    'type'       => 'store',
                    'width'      => '140px',
                    'store_view' => true,
                )
            );
        }

        $this->addColumn(
            'product_title', array(
                'header' => Mage::helper('channableapi')->__('Product'),
                'index'  => 'product_title',
            )
        );

        $this->addColumn(
            'is_in_stock', array(
                'header'  => Mage::helper('channableapi')->__('In Stock'),
                'index'   => 'is_in_stock',
                'width'   => '70px',
                'type'    => 'options',
                'options' => array(
                    '0' => Mage::helper('channableapi')->__('No'),
                    '1' => Mage::helper('channableapi')->__('Yes'),
                ),
            )
        );

        $this->addColumn(
            'delivery_time_nl', array(
                'header' => Mage::helper('channableapi')->__('Delivery NL'),
                'index'  => 'delivery_time_nl',
            )
        );

        $this->addColumn(
            'delivery_time_be', array(
                'header' => Mage::helper('channableapi')->__('Delivery BE'),
                'index'  => 'delivery_time_be',
            )
        );

        $this->addColumn(
            'price', array(
                'header'        => Mage::helper('channableapi')->__('Price'),
                'index'         => 'price',
                'type'          => 'price',
                'width'         => '80px',
                'currency_code' => $store->getBaseCurrency()->getCode(),
            )
        );

        $this->addColumn(
            'discount_price', array(
                'header'        => Mage::helper('channableapi')->__('Discount Price'),
                'index'         => 'discount_price',
                'type'          => 'price',
                'width'         => '80px',
                'currency_code' => $store->getBaseCurrency()->getCode(),
            )
        );

        $this->addColumn(
            'delivery_cost_nl', array(
                'header'        => Mage::helper('channableapi')->__('Shipping NL'),
                'index'         => 'delivery_cost_nl',
                'type'          => 'price',
                'width'         => '80px',
                'currency_code' => $store->getBaseCurrency()->getCode(),
            )
        );

        $this->addColumn(
            'delivery_cost_be', array(
                'header'        => Mage::helper('channableapi')->__('Shipping BE'),
                'index'         => 'delivery_cost_be',
                'type'          => 'price',
                'width'         => '80px',
                'currency_code' => $store->getBaseCurrency()->getCode(),
            )
        );

        $this->addColumn(
            'updated_at', array(
                'header' => Mage::helper('channableapi')->__('Updated At'),
                'type'   => 'datetime',
                'width'  => '100px',
                'index'  => 'updated_at',
            )
        );

        $this->addColumn(
            'last_call', array(
                'header' => Mage::helper('channableapi')->__('Last Call'),
                'type'   => 'datetime',
                'width'  => '100px',
                'index'  => 'last_call',
            )
        );

        $this->addColumn(
            'call_result', array(
                'header' => Mage::helper('channableapi')->__('Call Result'),
                'width'  => '50px',
                'index'  => 'call_result',
            )
        );

        $this->addColumn(
            'needs_update', array(
                'header'  => Mage::helper('channableapi')->__('Needs Update'),
                'index'   => 'needs_update',
                'width'   => '70px',
                'type'    => 'options',
                'options' => array(
                    '0' => Mage::helper('channableapi')->__('No'),
                    '1' => Mage::helper('channableapi')->__('Yes'),
                ),
            )
        );

        $this->addColumn(
            'action', array(
                'header'    => Mage::helper('channableapi')->__('Action'),
                'width'     => '100',
                'type'      => 'action',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption' => Mage::helper('channableapi')->__('Update now'),
                        'url'     => array('base' => '*/*/updateItem'),
                        'field'   => 'item_id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
            )
        );

        return parent::_prepareColumns();
    }

    /**
     * @return mixed
     */
    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);

        return Mage::app()->getStore($storeId);
    }

    /**
     * @return $this
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('item_id');
        $this->getMassactionBlock()->setFormFieldName('item_ids');
        $this->getMassactionBlock()->addItem(
            'invalidate', array(
                'label'   => Mage::helper('channableapi')->__('Que for update by cron'),
                'url'     => $this->getUrl('*/*/massInvalidate'),
                'confirm' => Mage::helper('channableapi')->__('Are you sure?')
            )
        );
        return $this;
    }

}