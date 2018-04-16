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

/** @var $installer Mage_Sales_Model_Mysql4_Setup */
$installer = new Mage_Sales_Model_Mysql4_Setup('core_setup');
$installer->startSetup();
$connection = $installer->getConnection();

$columnName = 'channable_id';
if ($installer->getAttributeId('order', $columnName)) {
    if ($connection->tableColumnExists($this->getTable('sales/order'), $columnName) === false) {
        $connection->addColumn(
            $this->getTable('sales/order'),
            $columnName,
            "int(11) DEFAULT '0' COMMENT 'Channable Id'"
        );
    }
    $installer->updateAttribute('order', 'channable_id', 'input', null);
}

$columnName = 'channel_id';
if ($installer->getAttributeId('order', $columnName)) {
    if ($connection->tableColumnExists($this->getTable('sales/order'), $columnName) === false) {
        $connection->addColumn(
            $this->getTable('sales/order'),
            $columnName,
            "varchar(255) DEFAULT NULL COMMENT 'Channel Id'"
        );
    }
}

$columnName = 'channel_name';
if ($installer->getAttributeId('order', $columnName)) {
    if ($connection->tableColumnExists($this->getTable('sales/order'), $columnName) === false) {
        $connection->addColumn(
            $this->getTable('sales/order'),
            $columnName,
            "varchar(255) DEFAULT NULL COMMENT 'Channel Name'"
        );
    }
}

$installer->endSetup();