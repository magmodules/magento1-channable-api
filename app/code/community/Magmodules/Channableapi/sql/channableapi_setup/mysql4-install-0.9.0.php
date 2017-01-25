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

$installer = new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');
$installer->startSetup();
$installer->run(
    "
	CREATE TABLE IF NOT EXISTS {$this->getTable('channable_items')} (
	`item_id` int(11) NOT NULL,
	`product_title` varchar(255) DEFAULT NULL,
	`product_id` int(11) NOT NULL,
	`store_id` smallint(5) DEFAULT NULL,
	`is_in_stock` int(11) NOT NULL,
	`price` decimal(12,4) NOT NULL,
	`qty` decimal(12,4) NOT NULL DEFAULT '0.0000',
	`discount_price` decimal(12,4) NOT NULL,
	`delivery_cost_nl` decimal(12,4) NOT NULL,
	`delivery_cost_be` decimal(12,4) NOT NULL,
	`delivery_time_nl` varchar(255) NOT NULL,
	`delivery_time_be` varchar(255) NOT NULL,
	`created_at` timestamp NULL DEFAULT NULL,
	`updated_at` timestamp NULL DEFAULT NULL,
	`last_call` timestamp NULL DEFAULT NULL,
	`call_result` varchar(255) DEFAULT NULL,
	`needs_update` smallint(5) NOT NULL DEFAULT '0',
	PRIMARY KEY (`item_id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
"
);

$installer->addAttribute(
    'order', 'channable_id', array(
        'type'             => 'int',
        'input'            => 'boolean',
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

$installer->endSetup(); 