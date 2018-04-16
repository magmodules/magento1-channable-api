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

/** @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */
$installer = new Mage_Sales_Model_Mysql4_Setup('core_setup');
$installer->startSetup();

$installer->addAttribute(
    'order', 'channable_id', array(
        'type'             => 'int',
        'default'          => 0,
        'label'            => 'Channable: Order ID',
        'visible'          => false,
        'required'         => false,
        'visible_on_front' => false,
        'user_defined'     => false,
    )
);

$installer->addAttribute(
    'order', 'channel_id', array(
        'type'             => 'varchar',
        'default'          => null,
        'label'            => 'Channable: Channel ID',
        'visible'          => false,
        'required'         => false,
        'visible_on_front' => false,
        'user_defined'     => false,
    )
);

$installer->addAttribute(
    'order', 'channel_name', array(
        'type'             => 'varchar',
        'default'          => null,
        'label'            => 'Channable: Imported From',
        'visible'          => false,
        'required'         => false,
        'visible_on_front' => false,
        'user_defined'     => false,
    )
);

$installer->run(
    sprintf(
        "CREATE TABLE IF NOT EXISTS `%s` (
		`item_id` BIGINT(11) NOT NULL,
		`title` varchar(255) DEFAULT NULL,
		`product_id` int(11) NOT NULL,
		`parent_id` int(11) NOT NULL,
		`gtin` varchar(255) DEFAULT NULL,
		`store_id` smallint(5) DEFAULT NULL,
		`is_in_stock` int(11) NOT NULL,
		`price` decimal(12,4) NOT NULL,
		`stock` decimal(12,4) NOT NULL DEFAULT '0.0000',
		`discount_price` decimal(12,4) NOT NULL,
		`delivery_cost_nl` decimal(12,4) NOT NULL,
		`delivery_cost_be` decimal(12,4) NOT NULL,
		`delivery_time_nl` varchar(255) NOT NULL,
		`delivery_time_be` varchar(255) NOT NULL,
		`created_at` timestamp NULL DEFAULT NULL,
		`updated_at` timestamp NULL DEFAULT NULL,
		`last_call` timestamp NULL DEFAULT NULL,
		`attempts` smallint(5) DEFAULT 0,
		`status` varchar(255) NOT NULL,
		`call_result` varchar(255) DEFAULT NULL,
		`needs_update` smallint(5) NOT NULL DEFAULT '0',
		PRIMARY KEY (`item_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
        $this->getTable('channable_items')
    )
);

$installer->run(
    sprintf(
        "CREATE TABLE IF NOT EXISTS `%s`(
		`id` bigint(20) NOT NULL AUTO_INCREMENT, 
		`ids` varchar(255) NOT NULL,
		`type` varchar(255) NOT NULL,
		`status` varchar(255) NOT NULL,
		`action` varchar(255) NOT NULL,
		`message` text NOT NULL,
		`created_time` datetime NOT NULL,
		PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
        $this->getTable('channable_debug')
    )
);

try {
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX product_id(product_id);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX store_id(store_id);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX created_at(created_at);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX updated_at(updated_at);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX needs_update(needs_update);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX parent_id(parent_id);");
} catch (Exception $e) {
    Mage::log('Channable Index (install):' . $e->getMessage());
}

$installer->endSetup();