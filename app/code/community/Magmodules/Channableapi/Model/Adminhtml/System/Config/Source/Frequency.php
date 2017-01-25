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

class Magmodules_Channableapi_Model_Adminhtml_System_Config_Source_Frequency
{

    protected static $_options;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (!self::$_options) {
            self::$_options = array(
                array('label' => Mage::helper('adminhtml')->__('-- Never'), 'value' => ''),
                array('label' => Mage::helper('adminhtml')->__('Every 15 minutes'), 'value' => '*/15'),
                array('label' => Mage::helper('adminhtml')->__('Every 5 minutes'), 'value' => '*/5'),
                array('label' => Mage::helper('adminhtml')->__('Every minute'), 'value' => '*'),
            );
        }

        return self::$_options;
    }

}