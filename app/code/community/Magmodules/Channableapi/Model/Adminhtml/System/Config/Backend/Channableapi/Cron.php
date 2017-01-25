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

class Magmodules_Channableapi_Model_Adminhtml_System_Config_Backend_Channableapi_Cron extends Mage_Core_Model_Config_Data
{

    const CRON_MODEL_PATH = 'channable_api/crons/cron_schedule';

    /**
     * @throws Exception
     */
    protected function _afterSave()
    {
        $frequency = $this->getData('groups/crons/fields/frequency/value');
        if ($frequency) {
            $cronExprArray = array($frequency, '*', '*', '*', '*');
            $cronExprString = join(' ', $cronExprArray);
        } else {
            $cronExprString = '';
        }

        try {
            Mage::getModel('core/config_data')->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue($cronExprString)->setPath(self::CRON_MODEL_PATH)->save();
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));
        }
    }

}