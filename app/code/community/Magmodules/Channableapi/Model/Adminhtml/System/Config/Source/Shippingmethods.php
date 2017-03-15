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

class Magmodules_Channableapi_Model_Adminhtml_System_Config_Source_Shippingmethods
{

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $activeCarriers = Mage::getSingleton('shipping/config')->getActiveCarriers();
        $allCarriers = array();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $options = array();
            $carrierTitle = Mage::getStoreConfig('carriers/' . $carrierCode . '/title');
            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_' . $methodCode;
                    $options[] = array('value' => $code, ' label' => $carrierTitle . ' ' . $method);
                    $allCarriers[] = array(
                        'value'      => $code,
                        'label'      => $carrierTitle . ' ' . $method,
                        'label_disp' => $carrierMethods[$methodCode]
                    );
                }
            }
        }

        $allCarriers[] = array(
            'value'      => 'custom',
            'label'      => 'Use Custom Logic'
        );

        return $allCarriers;
    }

} 