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
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Directory\Model\RegionFactory $regionFactory
    )
    {
        $this->_request = $request;
        $this->catalogSession = $catalogSession;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->regionFactory = $regionFactory;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        $this->pushOrder($order);
    }

    public function pushOrder($order) {

        $active = $this->scopeConfig->getValue('general/options/shiphawk_active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (!$active) {
            return;
        }

        $orderLineItems = [];
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
          $json = $this->_serializeItem($item);

          if (is_array($json)) {
            $orderLineItems[] = $json;
          }
        }

        $orderRequest = json_encode(
            array(
                'order_number' => $order->getIncrementId(),
                'source' => 'magento2',
                'source_system' => 'magento2',
                'source_system_id' => $order->getId(),
                'source_system_processed_at' => date("Y-m-d H:i:s"), //
                'requested_rate_id' => $shippingRateId,
                'requested_shipping_details'=> $order->getShippingDescription(),
                'origin_address' => $this->_getOriginAddress(),
                'destination_address' => $this->_prepareAddress($order->getShippingAddress()),
                'order_line_items' => $orderLineItems,
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

    protected function _serializeItem($item) {
      $item_weight = $item->getWeight();

      $json = array(
        'quantity' => $item->getQtyOrdered(),
        'value' => $item->getPrice(),
        'weight' => $item_weight,
        'weight_uom' => 'lbs',
        'can_ship_parcel' => true,
        'item_type' => 'parcel',
        'source_system_id' => $item->getItemId()
      );

      if ($item_weight > 70) {
        $json['handling_unit_type'] = 'box';
        $json['item_type'] = 'handling_unit';
      }

      if ($item->getProductType() != 'simple') {
        if ($option = $item->getProductOptions()) {
          $simple_sku = $option['simple_sku'];

          if ($product = $this->_loadProductBySku($simple_sku)) {
              $json['sku'] = $simple_sku;
              $json['length'] = $this->_getAttributeValue($product, 'shiphawk_length');
              $json['width'] = $this->_getAttributeValue($product, 'shiphawk_width');
              $json['height'] = $this->_getAttributeValue($product, 'shiphawk_height');

              return $json;
          }
        }
      } else if ($item->getProductType() != 'configurable' && !$item->getParentItemId()) {
        $product = $item->getProduct();

        $json['sku'] = $item->getSku();
        $json['length'] = $this->_getAttributeValue($product, 'shiphawk_length');
        $json['width'] = $this->_getAttributeValue($product, 'shiphawk_width');
        $json['height'] = $this->_getAttributeValue($product, 'shiphawk_height');

        return $json;
      }
    }

    protected function _getAttributeValue($product, $attribute) {
      $id = $product->getId();

      return floatval($product->getResource()->getAttributeRawValue($id, $attribute, null));
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

    protected function _prepareAddress($address) {
        $streets = $address->getStreet();

        return array(
            'name'              => $address->getFirstname() . ' ' . $address->getMiddlename() . ' ' . $address->getLastname(),
            'company'           => $address->getCompany(),
            'street1'           => $streets[0],
            'street2'           => isset($streets[1]) ? $streets[1] : "",
            'phone_number'      => $address->getTelephone(),
            'city'              => $address->getCity(),
            'state'             => $address->getRegionCode(),
            'country'           => $address->getCountryId(),
            'zip'               => $address->getPostcode(),
            'email'             => $address->getEmail(),
            'is_residential'    => 'true'
        );
    }

    protected function _getOriginAddress() {
      $shipperRegionId = $this->_getStoreInfo('region_id');
      $state = null;

      if (is_numeric($shipperRegionId)) {
          $shipperRegion = $this->regionFactory->create()->load($shipperRegionId);
          $state = $shipperRegion->getCode();
      }

      return array(
        'name' => $this->_getStoreInfo('name'),
        'phone_number' => $this->_getStoreInfo('phone'),
        'street1' => $this->_getStoreInfo('street_line1'),
        'street2' => $this->_getStoreInfo('street_line2'),
        'city' => $this->_getStoreInfo('city'),
        'state' => $state,
        'country' => $this->_getStoreInfo('country_id'),
        'zip' => $this->_getStoreInfo('postcode'),
      );
    }

    protected function _getStoreInfo($attribute) {
      return $this->scopeConfig->getValue("general/store_information/$attribute", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    protected function _loadProductBySku($sku) {
        return $this->productRepository->get($sku);
    }
}
