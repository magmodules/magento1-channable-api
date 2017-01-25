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

class Magmodules_Channableapi_Block_Adminhtml_Widget_Info_Info extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $magentoVersion = Mage::getVersion();
        $moduleVersion = Mage::getConfig()->getNode()->modules->Magmodules_Channableapi->version;
        $logoLink = '//www.magmodules.eu/logo/channableapi/' . $moduleVersion . '/' . $magentoVersion . '/logo.png';

        $html = '<div style="background:url(\'' . $logoLink . '\') no-repeat scroll 15px center #EAF0EE;border:1px solid #CCCCCC;margin-bottom:10px;padding:10px 5px 5px 200px;">
					<h4>About Magmodules.eu</h4>
					<p>We are a Magento only E-commerce Agency located in the Netherlands.<br>
                    <br />
                  	<table width="500px" border="0">
						<tr>
							<td width="58%">More Extensions from Magmodules:</td>
							<td width="42%"><a href="http://www.magentocommerce.com/magento-connect/developer/Magmodules" target="_blank">Magento Connect</a></td>
						</tr>
						<tr>
							<td>For Help:</td>
							<td><a href="https://www.magmodules.eu/support.html?ext=channable-order-api#">Visit our Support Page</a></td>
						</tr>
						<tr>
							<td height="30">Visit our Website:</td>
							<td><a href="http://www.magmodules.eu" target="_blank">www.Magmodules.eu</a></td>
						</tr>
						<tr>
							<td height="30"><strong>Read everything about the extension configuration in our <a href="http://www.magmodules.eu/help/channable-order-API" target="_blank">Knowledgebase</a></strong>.</td>
							<td>&nbsp;</td>
						</tr>
					</table>
                </div>';

        if (!Mage::getConfig()->getModuleConfig('Magmodules_Channable')->is('active', 'true')) {
            $html .= '<div id="messages"><ul class="messages"><li class="error-msg"><ul><li><span>';
            $html .= $this->__(
                'This extension does not work without the <a href="%s">Channable Feed extension</a>, please install the Channable Feed extension before enabeling the Channable API extension.',
                'https://www.magmodules.eu/channable-connect.html'
            );
            $html .= '</span></ul></li></ul></div>';
        }

        return $html;
    }

}