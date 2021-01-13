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

class Magmodules_Channableapi_Model_Order extends Mage_Core_Model_Abstract
{

    public $weight = 0;
    public $total = 0;

    /**
     * @param $order
     * @param $storeId
     *
     * @return mixed
     */
    public function importOrder($order, $storeId)
    {
        $config = $this->getConfig($storeId);
        $store = Mage::getModel('core/store')->load($storeId);
        $lvb = ($order['order_status'] == 'shipped') ? true : false;

        if ($errors = $this->checkProducts($order['products'], $lvb)) {
            return $this->jsonRepsonse($errors, '', $order['channable_id']);
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->setStoreId($config['store_id']);
        $quote->setBaseCurrencyCode($config['currency_code']);

        if ($config['import_customers']) {
            $customer = $this->importCustomer($order, $config);
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

        $billingAddress = $this->setQuoteAddress('billing', $order, $config, $customerId);
        if (!empty($billingAddress['errors'])) {
            return $billingAddress;
        } else {
            $quote->getBillingAddress()->addData($billingAddress);
        }

        $shippingAddress = $this->setQuoteAddress('shipping', $order, $config, $customerId);
        if (!empty($shippingAddress['errors'])) {
            return $shippingAddress;
        } else {
            $quote->getShippingAddress()->addData($shippingAddress);
        }

        $this->addProductsToQuote($quote, $order, $config, $store, $lvb);

        try {
            if (empty($config['shipping_includes_tax'])) {
                $taxId = Mage::getStoreConfig('tax/classes/shipping_tax_class', $storeId);
                $percent = $this->getTax($quote->getShippingAddress(), $quote->getBillingAddress(), $store, $taxId);
                $shippingPriceCal = ($order['price']['shipping'] / (100 + $percent) * 100);
            } else {
                $shippingPriceCal = $order['price']['shipping'];
            }

            // SET SESSION DATA (SHIPPING)
            Mage::getSingleton('core/session')->setChannableEnabled(1);
            Mage::getSingleton('core/session')->setChannableShipping($shippingPriceCal);

            $shippingMethod = $this->getShippingMethod($quote, $shippingAddress, $config);
            $quote->getShippingAddress()
                ->setShippingMethod($shippingMethod)
                ->setPaymentMethod($config['payment_method'])
                ->setCollectShippingRates(true)
                ->collectTotals();
            $quote->getPayment()->importData(array('method' => $config['payment_method']));
            $quote->save();

            /** @var Mage_Sales_Model_Service_Quote $service */
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $quote->setIsActive(false)->save();

            /** @var Mage_Sales_Model_Order $_order */
            if (!empty($config['channel_orderid'])) {
                $newIncrement = $this->getUniqueIncrementId($order['channel_id'], $storeId);
                $_order = $service->getOrder()
                    ->setIncrementId($newIncrement)
                    ->save();
            } else {
                $_order = $service->getOrder();
            }

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
            return $this->jsonRepsonse($e->getMessage(), '', $order['channable_id']);
        }

        if (!empty($config['invoice_order'])) {
            try {
                $invoice = $_order->prepareInvoice();
                $invoice->register();

                Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
                $_order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);

                if ($status = Mage::helper('channableapi')->getProcessingStatus($storeId)) {
                    $_order->setStatus($status);
                }

                $_order->save();
            } catch (Exception $e) {
                $this->addToLog('importOrder', $e->getMessage(), 2);
            }
        }

        if ($lvb && !empty($config['lvb_ship'])) {
            try {
                $shipment = $_order->prepareShipment();
                $shipment->register();

                $_order->setIsInProcess(true);
                $_order->addStatusHistoryComment('LVB Order, Automaticly Shipped', false);

                Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
            } catch (Exception $e) {
                $this->addToLog('importOrder', $e->getMessage(), 2);
            }
        }

        if (!empty($config['order_email'])) {
            try {
                $_order->getSendConfirmation(null);
                $_order->sendNewOrderEmail();
                $_order->save();
            } catch (Exception $e) {
                $this->addToLog('importOrder', $e->getMessage(), 2);
            }
        }

        // UNSET SESSION DATA (SHIPPING)
        Mage::getSingleton('core/session')->unsChannableEnabled();
        Mage::getSingleton('core/session')->unsChannableShipping();

        return $this->jsonRepsonse('', $_order->getIncrementId());
    }

    /**
     * @param $storeId
     *
     * @return array
     */
    public function getConfig($storeId)
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
        $config['order_email'] = Mage::getStoreConfig('channable_api/order/order_email', $storeId);
        $config['customers_group'] = Mage::getStoreConfig('channable_api/order/customers_group', $storeId);
        $config['channel_orderid'] = Mage::getStoreConfig('channable_api/order/channel_orderid', $storeId);
        $config['lvb_stock'] = Mage::getStoreConfig('channable_api/advanced/lvb_stock', $storeId);
        $config['lvb_ship'] = Mage::getStoreConfig('channable_api/advanced/lvb_stock', $storeId);
        $config['currency_code'] = Mage::getStoreConfig('currency/options/default', $storeId);
        return $config;
    }

    /**
     * @param $products
     * @param $lvb
     *
     * @return array|bool
     */
    public function checkProducts($products, $lvb)
    {
        $error = array();
        foreach ($products as $product) {
            /** @var Mage_Catalog_Model_Product $_product */
            $_product = Mage::getModel('catalog/product')->load($product['id']);
            if (!$_product->getId()) {
                if (!empty($product['title']) && !empty($product['id'])) {
                    $error[] = Mage::helper('channableapi')->__(
                        'Product "%s" not found in catalog (ID: %s)',
                        $product['title'],
                        $product['id']
                    );
                } else {
                    $error[] = Mage::helper('channableapi')->__('Product not found in catalog');
                }
            } else {
                if (!$_product->isSalable() && !$lvb) {
                    $error[] = Mage::helper('channableapi')->__(
                        'Product "%s" not available in requested quantity',
                        $product['title'],
                        $product['id']
                    );
                }
                $options = $_product->getRequiredOptions();
                if (!empty($options)) {
                    $error[] = Mage::helper('channableapi')->__(
                        'Product "%s" has required options, this is not supported (ID: %s)',
                        $product['title'],
                        $product['id']
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
    public function jsonRepsonse($errors = '', $orderId = '', $channableId = '')
    {
        return Mage::helper('channableapi')->jsonResponse($errors, $orderId, $channableId);
    }

    /**
     * @param $order
     * @param $config
     *
     * @return mixed
     */
    public function importCustomer($order, $config)
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
                return $this->jsonRepsonse($e->getMessage(), '', $order['channable_id']);
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
    public function setQuoteAddress($type, $order, $config, $customerId = '')
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

        $street = $this->getStreet($address, $config['seperate_housenumber']);

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
                return $this->jsonRepsonse($e->getMessage(), '', $order['channable_id']);
            }
        }

        return $addressData;
    }

    /**
     * @param $address
     * @param $seperateHousnumber
     *
     * @return array|string
     */
    public function getStreet($address, $seperateHousnumber)
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
     * @param Mage_Sales_Model_Quote    $quote
     * @param                           $order
     * @param                           $config
     * @param                           $store
     * @param bool                      $lvb
     */
    public function addProductsToQuote($quote, $order, $config, $store, $lvb = false)
    {
        foreach ($order['products'] as $product) {
            /** @var Mage_Catalog_Model_Product $_product */
            $_product = Mage::getModel('catalog/product')->load($product['id']);

            if (empty($config['price_includes_tax'])) {
                $taxId = $_product->getData('tax_class_id');
                $percent = $this->getTax($quote->getShippingAddress(), $quote->getBillingAddress(), $store,
                    $taxId);
                $price = ($product['price'] / (100 + $percent) * 100);
            } else {
                $price = $product['price'];
            }

            $this->total += $price;
            $this->weight += ($_product->getWeight() * $product['quantity']);

            if ($lvb && $config['lvb_stock']) {
                $_product->getStockItem()->setUseConfigManageStock(false);
                $_product->getStockItem()->setManageStock(false);
            }

            $buyRequest = new Varien_Object(array('qty' => $product['quantity']));
            $quote->addProduct($_product, $buyRequest)->setOriginalCustomPrice($price);
        }
    }

    /**
     * @param $shippingAddress
     * @param $billingAddress
     * @param $store
     * @param $taxId
     *
     * @return float
     */
    public function getTax($shippingAddress, $billingAddress, $store, $taxId)
    {
        /** @var Mage_Tax_Model_Calculation $taxCalculation */
        $taxCalculation = Mage::getSingleton('tax/calculation');
        $request = $taxCalculation->getRateRequest($shippingAddress, $billingAddress, null, $store);
        return $taxCalculation->getRate($request->setProductClassId($taxId));
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @param                        $shippingAddress
     * @param                        $config
     *
     * @return string
     */
    public function getShippingMethod($quote, $shippingAddress, $config)
    {
        $store = Mage::getModel('core/store')->load($config['store_id']);
        $shippingMethod = $config['shipping_method'];
        $shippingMethodFallback = $config['shipping_method_fallback'];
        if (!$shippingMethodFallback) {
            $shippingMethodFallback = 'flatrate_flatrate';
        }

        /** @var Mage_Shipping_Model_Rate_Request $request */
        $request = Mage::getModel('shipping/rate_request')
            ->setAllItems($quote->getAllItems())
            ->setDestCountryId($shippingAddress['country_id'])
            ->setDestPostcode($shippingAddress['postcode'])
            ->setPackageValue($this->total)
            ->setPackageValueWithDiscount($this->total)
            ->setPackageWeight($this->weight)
            ->setPackageQty(1)
            ->setPackagePhysicalValue($this->total)
            ->setFreeMethodWeight(0)
            ->setStoreId($store->getId())
            ->setWebsiteId($store->getWebsiteId())
            ->setFreeShipping(0)
            ->setBaseCurrency($store->getBaseCurrency())
            ->setBaseSubtotalInclTax($this->total);

        /** @var Mage_Shipping_Model_Shipping $model */
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
     * @param $channelId
     * @param $storeId
     *
     * @return mixed|string
     */
    public function getUniqueIncrementId($channelId, $storeId)
    {
        $prefix = Mage::getStoreConfig('channable_api/order/orderid_prefix', $storeId);
        $newIncrementId = $prefix . preg_replace("/[^a-zA-Z0-9]+/", "", $channelId);
        $orderCheck = Mage::getModel('sales/order')->loadByIncrementId($newIncrementId);
        if ($orderCheck->getId()) {
            /** @var Mage_Sales_Model_Order $lastOrder */
            $lastOrder = Mage::getModel('sales/order')->getCollection()
                ->addFieldToFilter('increment_id', array('like' => $newIncrementId . '-%'))
                ->addAttributeToSort('entity_id', 'ASC')
                ->getLastItem();
            if ($lastOrder->getIncrementId()) {
                $lastIncrement = explode('-', $lastOrder->getIncrementId());
                $newEnd = '-' . (end($lastIncrement) + 1);
                array_pop($lastIncrement);
                $newIncrementId = implode('-', $lastIncrement) . $newEnd;
            } else {
                $newIncrementId .= '-1';
            }
        }

        return $newIncrementId;
    }

    /**
     * @param      $type
     * @param      $msg
     * @param int  $level
     * @param bool $force
     *
     * @return mixed
     */
    public function addToLog($type, $msg, $level = 6, $force = false)
    {
        return Mage::helper('channableapi')->addToLog($type, $msg, $level, $force);
    }

    /**
     * @param $id
     *
     * @return array|mixed
     */
    public function getOrderById($id)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->loadByIncrementId($id);
        if (!$order->getId()) {
            return $this->jsonRepsonse($errors = 'No order found');
        }

        if ($order->getChannableId() < 1) {
            return $this->jsonRepsonse($errors = 'Not a Channable order');
        }

        $response = array();
        $response['id'] = $order->getIncrementId();
        $response['status'] = $order->getStatus();
        if ($tracking = $this->getTracking($order)) {
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
    public function getTracking($order)
    {
        $tracking = array();
        /** @var Mage_Sales_Model_Resource_Order_Shipment_Collection $shipmentCollection */
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

    /**
     * @param $timespan
     *
     * @return array
     */
    public function getShipments($timespan)
    {
        $response = array();
        $expression = sprintf('- %s hours', $timespan);
        $gmtDate = Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s');
        $date = date('Y-m-d H:i:s', strtotime($expression, strtotime($gmtDate)));

        $shipments = Mage::getResourceModel('sales/order_shipment_collection')
            ->addFieldToFilter('main_table.created_at', array('from' => $date))
            ->join(
                array('order' => 'sales/order'),
                'main_table.order_id=order.entity_id',
                array(
                    'order_increment_id' => 'order.increment_id',
                    'channable_id'       => 'order.channable_id',
                    'status'             => 'order.status'
                )
            )
            ->addFieldToFilter('channable_id', array('gt' => 0));

        foreach ($shipments as $shipment) {
            $data['id'] = $shipment->getOrderIncrementId();
            $data['status'] = $shipment->getStatus();
            $data['date'] = Mage::getModel('core/date')->date('Y-m-d H:i:s', $shipment->getCreatedAt());
            foreach ($shipment->getAllTracks() as $tracknum) {
                $data['fulfillment']['tracking_code'][] = $tracknum->getNumber();
                $data['fulfillment']['title'][] = $tracknum->getTitle();
                $data['fulfillment']['carrier_code'][] = $tracknum->getCarrierCode();
            }

            $response[] = $data;
            unset($data);
        }

        return $response;
    }
}