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

class Magmodules_Channableapi_Model_Order extends Mage_Core_Model_Abstract
{

    /**
     * @param $order
     * @param $storeId
     *
     * @return mixed
     */
    public function importOrder($order, $storeId)
    {
        $config = $this->_getConfig($storeId);
        $store = Mage::getModel('core/store')->load($storeId);

        if ($errors = $this->_checkProducts($order['products'])) {
            return $this->_jsonRepsonse($errors, '', $order['channable_id']);
        }

        $quote = Mage::getModel('sales/quote')->setStoreId($config['store_id']);

        if ($config['import_customers']) {
            $customer = $this->_importCustomer($order, $config);
            if (!empty($customer['errors'])) {
                return $customer;
            }

            $quote->assignCustomer($customer)->save();
            $customerId = $customer->getId();
        } else {
            if (!empty($order['customer']['middle_name'])) {
                $lastname = $order['customer']['middle_name'] . ' ' . $order['customer']['last_name'];
            } else {
                $lastname = $order['customer']['last_name'];
            }

            $quote->setCustomerEmail($order['customer']['email'])
                ->setCustomerFirstname($order['customer']['first_name'])
                ->setCustomerLastname($lastname)
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
                ->save();
            $customerId = '';
        }

        $billingAddress = $this->_setQuoteAddress('billing', $order, $config, $customerId);
        if (!empty($billingAddress['errors'])) {
            return $billingAddress;
        } else {
            $quote->getBillingAddress()->addData($billingAddress);
        }

        $shippingAddress = $this->_setQuoteAddress('shipping', $order, $config, $customerId);
        if (!empty($shippingAddress['errors'])) {
            return $shippingAddress;
        } else {
            $quote->getShippingAddress()->addData($shippingAddress);
        }

        $taxCalculation = Mage::getSingleton('tax/calculation');
        $total = 0;
        $weight = 0;

        foreach ($order['products'] as $product) {
            $_product = Mage::getModel('catalog/product')->load($product['id']);
            $price = $product['price'];

            // PRICES WITHOUT VAT
            if (empty($config['price_includes_tax'])) {
                $request = $taxCalculation
                    ->getRateRequest($quote->getShippingAddress(), $quote->getBillingAddress(), null, $store);
                $taxclassid = $_product->getData('tax_class_id');
                $percent = $taxCalculation->getRate($request->setProductClassId($taxclassid));
                $price = ($product['price'] / (100 + $percent) * 100);
            }

            $total = ($total + $price);
            $weight = ($weight + ($_product->getWeight() * $product['quantity']));
            $quote->addProduct(
                $_product,
                new Varien_Object(array('qty' => $product['quantity']))
            )->setOriginalCustomPrice($price);
        }

        try {
            if (empty($config['shipping_includes_tax'])) {
                $request = $taxCalculation
                    ->getRateRequest($quote->getShippingAddress(), $quote->getBillingAddress(), null, $store);
                $taxRateId = Mage::getStoreConfig('tax/classes/shipping_tax_class', $storeId);
                $percent = $taxCalculation->getRate($request->setProductClassId($taxRateId));
                $shippingPriceCal = ($order['price']['shipping'] / (100 + $percent) * 100);
            } else {
                $shippingPriceCal = $order['price']['shipping'];
            }

            // SET SESSION DATA (SHIPPING)
            Mage::getSingleton('core/session')->setChannableEnabled(1);
            Mage::getSingleton('core/session')->setChannableShipping($shippingPriceCal);

            $shippingMethod = $this->_getShippingMethod($quote, $shippingAddress, $total, $weight, $config);
            $quote->getShippingAddress()
                ->setShippingMethod($shippingMethod)
                ->setPaymentMethod($config['payment_method'])
                ->setCollectShippingRates(true)
                ->collectTotals();
            $quote->getPayment()->importData(array('method' => $config['payment_method']));
            $quote->save();
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $quote->setIsActive(false)->save();
            $_order = $service->getOrder();
            if (!empty($order['channel_name'])) {
                if (!empty($order['price']['commission'])) {
                    $commission = $order['price']['currency'] . ' ' . $order['price']['commission'];
                } else {
                    $commission = 'n/a';
                }

                $orderComment = Mage::helper('channableapi')->__(
                    '<b>%s order</b><br/>Channable id: %s<br/>%s id: %s<br/>Commission: %s',
                    ucfirst($order['channel_name']),
                    $order['channable_id'],
                    ucfirst($order['channel_name']),
                    $order['channel_id'],
                    $commission
                );
                $_order->addStatusHistoryComment($orderComment, false);
                $_order->setChannableId($order['channable_id'])
                    ->setChannelId($order['channel_id'])
                    ->setChannelName($order['channel_name'])
                    ->save();
            } elseif (!empty($order['channable_id'])) {
                $_order->setChannableId($order['channable_id'])->save();
            }

            unset($quote);
            unset($customer);
            unset($service);
        } catch (Exception $e) {
            return $this->_jsonRepsonse($e->getMessage(), '', $order['channable_id']);
        }

        if (!empty($config['invoice_order'])) {
            $invoice = $_order->prepareInvoice();
            $invoice->register();

            Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
            $_order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
            $_order->save();
        }

        // UNSET SESSION DATA (SHIPPING)
        Mage::getSingleton('core/session')->unsChannableEnabled();
        Mage::getSingleton('core/session')->unsChannableShipping();

        return $this->_jsonRepsonse('', $_order->getIncrementId());
    }

    /**
     * @param $storeId
     *
     * @return array
     */
    protected function _getConfig($storeId)
    {
        $config = array();
        $config['store_id'] = $storeId;
        $config['payment_method'] = 'channable';
        $config['shipping_method'] = Mage::getStoreConfig('channable_api/order/shipping_method', $storeId);
        $config['shipping_method_fallback'] =
            Mage::getStoreConfig('channable_api/order/shipping_method_fallback', $storeId);
        $config['shipping_method_custom'] =
            Mage::getStoreConfig('channable_api/order/shipping_method_custom', $storeId);
        $config['import_customers'] = Mage::getStoreConfig('channable_api/order/import_customers', $storeId);
        $config['customers_group'] = Mage::getStoreConfig('channable_api/order/customers_group', $storeId);
        $config['customers_mailing'] = Mage::getStoreConfig('channable_api/order/customers_mailing', $storeId);
        $config['shipping_includes_tax'] = Mage::getStoreConfig('tax/calculation/shipping_includes_tax', $storeId);
        $config['price_includes_tax'] = Mage::getStoreConfig('tax/calculation/price_includes_tax', $storeId);
        $config['seperate_housenumber'] = Mage::getStoreConfig('channable_api/order/seperate_housenumber', $storeId);
        $config['invoice_order'] = Mage::getStoreConfig('channable_api/order/invoice_order', $storeId);
        $config['customers_group'] = Mage::getStoreConfig('channable_api/order/customers_group', $storeId);

        return $config;
    }

    /**
     * @param $products
     *
     * @return array|bool
     */
    protected function _checkProducts($products)
    {
        $error = array();
        foreach ($products as $product) {
            $_product = Mage::getModel('catalog/product')->load($product['id']);
            if (!$_product->getId()) {
                if (!empty($product['title']) && !empty($product['id'])) {
                    $error[] = Mage::helper('channableapi')->__(
                        'Product "%s" not found in catalog (ID: %s)',
                        $product['title'], $product['id']
                    );
                } else {
                    $error[] = Mage::helper('channableapi')->__('Product not found in catalog');
                }
            } else {
                if (!$_product->isSalable()) {
                    $error[] = Mage::helper('channableapi')->__(
                        'Product "%s" not available in requested quantity',
                        $product['title'], $product['id']
                    );
                }

                $options = $_product->getRequiredOptions();
                if (!empty($options)) {
                    $error[] = Mage::helper('channableapi')->__(
                        'Product "%s" has required options, this is not supported (ID: %s)',
                        $product['title'], $product['id']
                    );
                }
            }
        }

        if (!empty($error)) {
            return $error;
        } else {
            return false;
        }
    }

    /**
     * @param string $errors
     * @param string $orderId
     * @param string $channableId
     *
     * @return mixed
     */
    protected function _jsonRepsonse($errors = '', $orderId = '', $channableId = '')
    {
        return Mage::helper('channableapi')->jsonResponse($errors, $orderId, $channableId);
    }

    /**
     * @param $order
     * @param $config
     *
     * @return mixed
     */
    protected function _importCustomer($order, $config)
    {
        $store = Mage::getModel('core/store')->load($config['store_id']);
        $websiteId = Mage::getModel('core/store')->load($config['store_id'])->getWebsiteId();
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($order['customer']['email']);
        if (!$customer->getId()) {
            $customer->setEmail($order['customer']['email']);
            $customer->setFirstname($order['customer']['first_name']);
            $customer->setMiddlename($order['customer']['middle_name']);
            $customer->setLastname($order['customer']['last_name']);
            $customer->setWebsiteId($websiteId);
            $customer->setStore($store);
            $customer->setGroupId($config['customers_group']);
            $newPassword = $customer->generatePassword();
            $customer->setPassword($newPassword);
            try {
                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();

                return $customer;
            } catch (Exception $e) {
                return $this->_jsonRepsonse($e->getMessage(), '', $order['channable_id']);
            }
        } else {
            return $customer;
        }
    }

    /**
     * @param        $type
     * @param        $order
     * @param        $config
     * @param string $customerId
     *
     * @return array|mixed
     */
    protected function _setQuoteAddress($type, $order, $config, $customerId = '')
    {
        if ($type == 'billing') {
            $address = $order['billing'];
        } else {
            $address = $order['shipping'];
        }

        $telephone = '000';
        if (!empty($order['customer']['phone'])) {
            $telephone = $order['customer']['phone'];
        }

        if (!empty($order['customer']['mobile'])) {
            $telephone = $order['customer']['mobile'];
        }

        $street = $this->_getStreet($address, $config['seperate_housenumber']);

        $state = '';
        if (!empty($address['state'])) {
            $state = $address['state'];
        }

        $addressData = array(
            'customer_id' => $customerId,
            'company'     => $address['company'],
            'firstname'   => $address['first_name'],
            'middlename'  => $address['middle_name'],
            'lastname'    => $address['last_name'],
            'email'       => $order['customer']['email'],
            'street'      => $street,
            'city'        => $address['city'],
            'country_id'  => $address['country_code'],
            'postcode'    => $address['zip_code'],
            'telephone'   => $telephone,
            'region'      => $state,
        );

        if (!empty($config['import_customers'])) {
            $addressData['save_in_address_book'] = 1;
        }

        if (!empty($config['import_customers']) && ($customerId > 0)) {
            try {
                if ($type == 'billing') {
                    Mage::getModel("customer/address")
                        ->setData($addressData)
                        ->setCustomerId($customerId)
                        ->setIsDefaultBilling('1')
                        ->setSaveInAddressBook('1')
                        ->save();
                } else {
                    Mage::getModel("customer/address")
                        ->setData($addressData)
                        ->setCustomerId($customerId)
                        ->setIsDefaultShipping('1')
                        ->setSaveInAddressBook('1')
                        ->save();
                }
            } catch (Exception $e) {
                return $this->_jsonRepsonse($e->getMessage(), '', $order['channable_id']);
            }
        }

        return $addressData;
    }

    protected function _getStreet($address, $seperateHousnumber)
    {
        $street = array();
        if (!empty($seperateHousnumber)) {
            $street[] = $address['street'];
            $street[] = trim($address['house_number'] . ' ' . $address['house_number_ext']);
            $street = implode("\n", $street);
        } else {
            if (!empty($address['address_line_1'])) {
                $street[] = $address['address_line_1'];
                $street[] = $address['address_line_2'];
                $street = implode("\n", $street);
            } else {
                $street = $address['street'] . ' ';
                $street .= trim($address['house_number'] . ' ' . $address['house_number_ext']);
            }
        }

        return $street;
    }

    /**
     * @param $quote
     * @param $shippingAddress
     * @param $total
     * @param $weight
     * @param $config
     *
     * @return string
     */
    protected function _getShippingMethod($quote, $shippingAddress, $total, $weight, $config)
    {
        $store = Mage::getModel('core/store')->load($config['store_id']);
        $shippingMethod = $config['shipping_method'];
        $shippingMethodFallback = $config['shipping_method_fallback'];
        if (!$shippingMethodFallback) {
            $shippingMethodFallback = 'flatrate_flatrate';
        }

        $request = Mage::getModel('shipping/rate_request')
            ->setAllItems($quote->getAllItems())
            ->setDestCountryId($shippingAddress['country_id'])
            ->setDestPostcode($shippingAddress['postcode'])
            ->setPackageValue($total)
            ->setPackageValueWithDiscount($total)
            ->setPackageWeight($weight)
            ->setPackageQty(1)
            ->setPackagePhysicalValue($total)
            ->setFreeMethodWeight(0)
            ->setStoreId($store->getId())
            ->setWebsiteId($store->getWebsiteId())
            ->setFreeShipping(0)
            ->setBaseCurrency($store->getBaseCurrency())
            ->setBaseSubtotalInclTax($total);

        $model = Mage::getModel('shipping/shipping')->collectRates($request);

        if ($shippingMethod != 'custom') {
            $defaultCarrier = explode('_', $shippingMethod);
            $fallbackCarrier = explode('_', $shippingMethodFallback);
            $carrierPrice = '';
            $fallbackPrice = '';
            foreach ($model->getResult()->getAllRates() as $rate) {
                $carriercode = $rate->getCarrier();
                $method = $rate->getMethod();
                $price = $rate->getPrice();
                if ($carriercode == $defaultCarrier[0]) {
                    if (empty($carrierPrice) || ($price > $carrierPrice)) {
                        $carrier = $carriercode . '_' . $method;
                    }
                }

                if ($carriercode == $fallbackCarrier[0]) {
                    if (empty($fallbackPrice) || ($price > $fallbackPrice)) {
                        $fallback = $carriercode . '_' . $method;
                    }
                }
            }
        } else {
            $prioritizedMethods = array_flip(array_reverse(explode(';', $config['shipping_method_custom'])));
            $priority = -1;
            foreach ($model->getResult()->getAllRates() as $rate) {
                $carriercode = $rate->getCarrier();
                $method = $rate->getMethod();
                $carrierMethod = $carriercode . '_' . $method;
                if (isset($prioritizedMethods[$carrierMethod]) && $priority < $prioritizedMethods[$carrierMethod]) {
                    $carrier = $carrierMethod;
                    $priority = $prioritizedMethods[$carrierMethod];
                }
            }
        }

        if (!empty($carrier)) {
            return $carrier;
        }

        if (!empty($fallback)) {
            return $fallback;
        }

        return $shippingMethodFallback;
    }

    /**
     * @param $id
     *
     * @return array|mixed
     */
    public function getOrderById($id)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($id);
        if (!$order->getId()) {
            return $this->_jsonRepsonse($errors = 'No order found');
        }

        if ($order->getChannableId() < 1) {
            return $this->_jsonRepsonse($errors = 'Not a Channable order');
        }

        $response = array();
        $response['id'] = $order->getIncrementId();
        $response['status'] = $order->getStatus();
        if ($tracking = $this->_getTracking($order)) {
            foreach ($tracking as $track) {
                $response['fulfillment']['tracking_code'][] = $track['tracking'];
                $response['fulfillment']['title'][] = $track['title'];
                $response['fulfillment']['carrier_code'][] = $track['carrier_code'];
            }
        }

        return $response;
    }

    /**
     * @param $order
     *
     * @return array|bool
     */
    protected function _getTracking($order)
    {
        $tracking = array();
        $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')->setOrderFilter($order)->load();
        foreach ($shipmentCollection as $shipment) {
            foreach ($shipment->getAllTracks() as $tracknum) {
                $tracking[] = array(
                    'tracking'     => $tracknum->getNumber(),
                    'title'        => $tracknum->getTitle(),
                    'carrier_code' => $tracknum->getCarrierCode()
                );
            }
        }

        if (!empty($tracking)) {
            return $tracking;
        } else {
            return false;
        }
    }

}