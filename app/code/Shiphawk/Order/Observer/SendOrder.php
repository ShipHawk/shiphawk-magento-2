<?php

namespace Shiphawk\Order\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;


class SendOrder implements ObserverInterface
{
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Catalog\Model\Session $catalogSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    )
    {
        $this->_request = $request;
        $this->catalogSession = $catalogSession;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;

    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $this->pushOrder($order);
    }

    public function pushOrder($order) {

        $active = $this->scopeConfig->getValue('general/options/shiphawk_active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (!$active) {
            return;
        }

        $itemsRequest = [];
        $shippingRateId = '';

        $SHRate = $this->catalogSession->getSHRate();

        if(is_array($SHRate)) {
            foreach($SHRate as $rateRow){
                if(($rateRow->carrier . ' - ' . $rateRow->service_name)  == $order->getShippingDescription()){
                    $shippingRateId = $rateRow->id;
                }
            }
        }

        foreach ($order->getAllItems() as $item) {

            if ($item->getProductType() != 'simple') {

                if ($option = $item->getProductOptions()) {
                $simple_sku = $option['simple_sku'];
                    if ($option = $this->_loadProductBySku($simple_sku)) {
                        $item_weight = $item->getWeight();
                        $new_item = array(
                            'sku' => $simple_sku,
                            'quantity' => $item->getQtyOrdered(),
                            'value' => $item->getPrice(),
                            'length' => floatval($option->getResource()->getAttributeRawValue($option->getId(),'shiphawk_length', null)),
                            'width' => floatval($option->getResource()->getAttributeRawValue($option->getId(),'shiphawk_width', null)),
                            'height' => floatval($option->getResource()->getAttributeRawValue($option->getId(),'shiphawk_height', null)),
                            'weight' => $item_weight <= 70 ? $item_weight * 16 : $item_weight,
                            'can_ship_parcel' => true,
                            'item_type' => $item_weight <= 70 ? 'parcel' : 'handling_unit',
                            'source_system_id' => $item->getId()
                        );
                        if ($item_weight > 70) {
                            $new_item['handling_unit_type'] = 'box';
                        }
                        $itemsRequest[] = $new_item;
                    }

                }

            } else if ($item->getProductType() != 'configurable' && !$item->getParentItemId()) {

                $item_weight = $item->getWeight();
                $new_item = array(
                    'sku' => $item->getSku(),
                    'quantity' => $item->getQtyOrdered(),
                    'value' => $item->getPrice(),
                    'length' => floatval($item->getProduct()->getResource()->getAttributeRawValue($item->getProduct()->getId(),'shiphawk_length', null)),
                    'width' => floatval($item->getProduct()->getResource()->getAttributeRawValue($item->getProduct()->getId(),'shiphawk_width', null)),
                    'height' => floatval($item->getProduct()->getResource()->getAttributeRawValue($item->getProduct()->getId(),'shiphawk_height', null)),
                    'weight' => $item_weight <= 70 ? $item_weight * 16 : $item_weight,
                    'can_ship_parcel' => true,
                    'item_type' => $item_weight <= 70 ? 'parcel' : 'handling_unit',
                    'source_system_id' => $item->getProductId()
                );

                if ($item_weight > 70) {
                    $new_item['handling_unit_type'] = 'box';
                }

                $itemsRequest[] = $new_item;
            }
        }

        $orderRequest = json_encode(
            array(
                'order_number' => $order->getIncrementId(),
                'source' => 'magento',
                'source_system' => 'magento',
                'source_system_id' => $order->getIncrementId(),
                'source_system_processed_at' => date("Y-m-d H:i:s"), //
                'requested_rate_id' => $shippingRateId,
                'requested_shipping_details'=> $order->getShippingDescription(),
                'origin_address' => $this->_getOriginAddress(),
                'destination_address' => $this->_prepareAddress($order->getShippingAddress()),
                'order_line_items' => $itemsRequest,
                'total_price' => $order->getGrandTotal(),
                'shipping_price' => $order->getShippingAmount(),
                'tax_price' => $order->getTaxAmount(),
                'items_price' => $order->getSubtotal(),
                'status' => 'new',
            )
        );

        try {
            $response = $this->_push($orderRequest);
            $this->mlog($response, 'response.log');

        } catch (Exception $e) {
            $this->mlog($e->getMessage(), 'error.log');
        }
    }

    protected function _push($jsonOrderRequest) {

        $api_key = $this->scopeConfig->getValue('general/options/shiphawk_api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $gateway_url = $this->scopeConfig->getValue('general/options/shiphawk_gateway_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $params = http_build_query(['api_key' => $api_key]);
        $ch_url = $gateway_url . 'orders' . '?' . $params;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $ch_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonOrderRequest);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonOrderRequest)
            )
        );

        $resp = curl_exec($ch);
        $arr_res = json_decode($resp);

        curl_close($ch);
        return $arr_res;
    }

    public function mlog($data, $file_mame = 'custom.log') {

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$file_mame);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info(var_export($data, true));
    }

    protected function _prepareAddress($address)
    {
        return array(
            'name'              => $address->getFirstname() . ' ' . $address->getMiddlename() . ' ' . $address->getLastname(),
            'company'           => $address->getCompany(),
            'street1'           => $address->getStreet1(),
            'street2'           => $address->getStreet2(),
            'phone_number'      => $address->getTelephone(),
            'city'              => $address->getCity(),
            'state'             => $address->getRegionCode(),
            'country'           => $address->getCountryId(),
            'zip'               => $address->getPostcode(),
            'email'             => $address->getEmail(),
            'is_residential'    => 'true'
        );
    }

    protected function _getOriginAddress()
    {
        return array(
            'name' => $this->scopeConfig->getValue('general/store_information/name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'phone_number' => $this->scopeConfig->getValue('general/store_information/phone',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'street1' => $this->scopeConfig->getValue('general/store_information/street_line1',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'street2' => $this->scopeConfig->getValue('general/store_information/street_line2',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'city' => $this->scopeConfig->getValue('general/store_information/city',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'state' => $this->scopeConfig->getValue('general/store_information/region_id',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'country' => $this->scopeConfig->getValue('general/store_information/country_id',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'zip' => $this->scopeConfig->getValue('general/store_information/postcode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
        );
    }

    protected function _loadProductBySku($sku)
    {
        return $this->productRepository->get($sku);
    }
}