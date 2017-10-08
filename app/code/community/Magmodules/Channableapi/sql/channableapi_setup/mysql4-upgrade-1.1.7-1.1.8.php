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

$installer = $this;
$installer->startSetup();

try {
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX product_id(product_id);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX store_id(store_id);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX created_at(created_at);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX updated_at(updated_at);");
    $installer->run("ALTER TABLE {$this->getTable('channable_items')} ADD INDEX needs_update(needs_update);");
} catch (Exception $e) {
    Mage::log('Channable Index (install):' . $e->getMessage());
}

$installer->endSetup();