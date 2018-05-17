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

class Magmodules_Channableapi_Helper_Selftest extends Magmodules_Channableapi_Helper_Data
{

    const SUPPORT_URL = 'https://www.magmodules.eu/help/channable-connect/channable-selftest-results';
    const GITHUB_URL = 'https://api.github.com/repos/magmodules/magento1-channable-api/tags';
    const GITHUB_CHANABLE_API_URL = 'https://github.com/magmodules/magento1-channable-api/releases';
    const GITHUB_CHANABLE_URL = 'https://github.com/magmodules/magento1-channable/releases';

    /**
     *
     */
    public function runTests()
    {
        $result = array();

        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $result[] = $this->getPass('Compatible PHP version: ' . PHP_VERSION);
        } else {
            $result[] = $this->getFail('Module requires PHP version >= 5.4, current version: ' . PHP_VERSION);
        }

        if ($this->getEnabled()) {
            $result[] = $this->getPass('Channable API Module Enabled');
        } else {
            $result[] = $this->getFail('Channable API Module not Enabled');
        }

        if ($this->isChannableInstalled()) {
            $result[] = $this->getPass('Channable Feed Module Installed');
        } else {
            $msg = $this->__(
                'Required Channable Feed Module Missing! %s',
                '<a href="' . self::GITHUB_CHANABLE_URL . '">[' . $this->__('Download') . ']</a>'
            );
            $result[] = $this->getFail($msg);
        }

        if (!$this->getToken()) {
            $url = Mage::helper("adminhtml")->getUrl('adminhtml/channable/createToken');
            $msg = $this->__('Token missing, <a href="%s">create new</a>', $url);
            $result[] = $this->getFail($msg, '#tokenmissing');
        }

        if ($this->isChannableInstalled()) {
            $itemStores = $this->getEnabledItemStores(false);
            if (!empty($itemStores)) {
                $result[] = $this->getPass('Enabled Item Store IDs: ' . implode(',', $itemStores) . '');
            } else {
                $result[] = $this->getFail('No Stores enabled for Item Updates!', '#item-updates');
            }

            foreach ($itemStores as $storeId) {
                if (!$this->getItemUpdateWebhook($storeId)) {
                    $result[] = $this->getFail('Item Webhook missing for store: ' . $storeId, '#webhook');
                }
            }
        }

        if ($this->getCronExpression()) {
            $result[] = $this->getPass('Channable Item API cron enabled');
        } else {
            $result[] = $this->getFail('Channable Item API cron not enabled!', '#item-cron');
        }

        if ($lastRun = $this->checkMagentoCron()) {
            if ((time() - strtotime($lastRun)) > 3600) {
                $msg = $this->__('Magento cron not seen in last hour (last: %s)', $lastRun);
                $result[] = $this->getFail($msg, '#cron');
            } else {
                $msg = $this->__('Magento cron seems to be running (last: %s)', $lastRun);
                $result[] = $this->getPass($msg);
            }
        } else {
            $result[] = $this->getFail('Magento cron not setup', '#cron');
        }

        $latestVersion = $this->latestVersion();
        if (isset($latestVersion['version'])) {
            $modulesArray = (array)Mage::getConfig()->getNode('modules')->children();
            $currentVersion = $modulesArray['Magmodules_Channableapi']->version;
            if (version_compare($currentVersion, $latestVersion['version']) >= 0) {
                $msg = $this->__('Running the latest version (Installed: v%s - Github: v%s)', $currentVersion,
                    $latestVersion['version']);
                $result[] = $this->getPass($msg);
            } else {
                $msg = $this->__('v%s is latest version, currenlty running v%s. %s',
                    $latestVersion['version'],
                    $currentVersion,
                    '<a href="' . self::GITHUB_CHANABLE_API_URL . '">[' . $this->__('Download') . ']</a>');
                $result[] = $this->getNotice($msg, '#update');
            }
        } else {
            $result[] = $this->getFail($latestVersion['error'], '#update-error');
        }

        if ($apiModule = $this->checkChannableFeedModule()) {
            if (!empty($apiModule['success'])) {
                $result[] = $this->getPass($apiModule['success']);
            }
            if (!empty($apiModule['error'])) {
                $result[] = $this->getFail($apiModule['error']);
            }
        }

        return $result;
    }

    /**
     * @param        $msg
     * @param string $link
     *
     * @return string
     */
    public function getPass($msg, $link = null)
    {
        return $this->getHtmlResult($msg, 'pass', $link);
    }

    /**
     * @param        $msg
     * @param        $type
     * @param string $link
     *
     * @return string
     */
    public function getHtmlResult($msg, $type, $link)
    {
        $format = null;

        if ($type == 'pass') {
            $format = '<span class="channableapi-success">%s</span>';
        }
        if ($type == 'fail') {
            $format = '<span class="channableapi-error">%s</span>';
        }
        if ($type == 'notice') {
            $format = '<span class="channableapi-notice">%s</span>';
        }

        if ($format) {
            if ($link) {
                $format = str_replace('</span>', '<span class="more"><a href="%s">More Info</a></span></span>',
                    $format);
                return sprintf($format, Mage::helper('channableapi')->__($msg), self::SUPPORT_URL . $link);
            } else {
                return sprintf($format, Mage::helper('channableapi')->__($msg));
            }
        }
    }

    /**
     * @param        $msg
     * @param string $link
     *
     * @return string
     */
    public function getFail($msg, $link = null)
    {
        return $this->getHtmlResult($msg, 'fail', $link);
    }

    /**
     *
     */
    public function checkMagentoCron()
    {
        $tasks = Mage::getModel('cron/schedule')->getCollection()
            ->addFieldToSelect('finished_at')
            ->addFieldToFilter('status', 'success');

        $tasks->getSelect()
            ->limit(1)
            ->order('finished_at DESC');

        return $tasks->getFirstItem()->getFinishedAt();
    }

    /**
     * @return array
     */
    public function latestVersion()
    {
        $version = null;
        $error = null;

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::GITHUB_URL);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Version Check Magento 1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $data = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode >= 200 && $httpcode < 300) {
                $data = json_decode($data, true);
                if (isset($data[0]['name'])) {
                    $version = str_replace(array('v.', 'v'), '', $data[0]['name']);
                }
            }
        } catch (\Exception $exception) {
            $error = $exception->getMessage();
            return array('error' => $this->__('Could not fetch latest version from Github, error: %s', $error));
        }

        if ($version) {
            return array('version' => $version);
        } else {
            return array('error' => $this->__('Could not fetch latest version from Github'));
        }
    }

    /**
     * @param        $msg
     * @param string $link
     *
     * @return string
     */
    public function getNotice($msg, $link = null)
    {
        return $this->getHtmlResult($msg, 'notice', $link);
    }

    /**
     * @return array|bool
     */
    public function checkChannableFeedModule()
    {
        $modulesArray = (array)Mage::getConfig()->getNode('modules')->children();

        if (!isset($modulesArray['Magmodules_Channable'])) {
            return false;
        }

        $currentVersion = $modulesArray['Magmodules_Channable']->version;

        if (version_compare($currentVersion, self::FEED_MIN_REQUIREMENT) >= 0) {
            return array(
                'success' => $this->__(
                    'Running minumum required Channable Feed Module (Installed: v%s - Min. required: v%s)',
                    $currentVersion,
                    self::FEED_MIN_REQUIREMENT)
            );
        } else {
            return array(
                'error' => $this->__(
                    'Channable Feed Module needs update (Installed: v%s - Min. required: v%s). %s',
                    $currentVersion,
                    self::FEED_MIN_REQUIREMENT,
                    '<a href="' . self::GITHUB_CHANABLE_URL . '">[' . $this->__('Download') . ']</a>'
                )
            );
        }
    }
}