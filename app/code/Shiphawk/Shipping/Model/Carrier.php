<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Shiphawk\Shipping\Model;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = 'shiphawk';

    protected $catalogSession;

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;
    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;
    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param array $data
     */

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        \Magento\Catalog\Model\Session $catalogSession,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->catalogSession = $catalogSession;

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }
    /**
     * Generates list of allowed carrier`s shipping methods
     * Displays on cart price rules page
     *
     * @return array
     * @api
     */
    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => __($this->getConfigData('title'))];
    }
    /**
     * Collect and get rates for storefront
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param RateRequest $request
     * @return DataObject|bool|null
     * @api
     */
    public function collectRates(RateRequest $request)
    {
        /**
         * Make sure that Shipping method is enabled
         */
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $result = $this->rateResultFactory->create();

        $items = $this->getItems($request);
        $origin_zip = Config::XML_PATH_ORIGIN_POSTCODE;

        $rateRequest = array(
            'items' => $items,
            'origin_address'=> array(
                'state'=> $this->getOrigRegionCode(),
                'country' => $this->getOriginCountryCode($request),
                'city' => $this->getOriginCity($request),
                'zip'=> $this->scopeConfig->getValue($origin_zip)
            ),
            'destination_address'=> array(
                'zip' => $request->getDestPostcode(),
                'country' => $request->getDestCountryId(),
                'state' => $request->getDestRegionCode(),
                'city' => $request->getDestCity(),
                'street1' => $request->getDestStreet(),
                'is_residential' => 'true'
            ),
            'apply_rules'=>'true'
        );

        $rateResponse = $this->getRates($rateRequest);

        if(property_exists($rateResponse, 'error')) {
            $this->logger->addError(var_export($rateResponse->error, true));
        }else{
            if($rateResponse && isset($rateResponse->rates)) {

                $this->catalogSession->setSHRate($rateResponse->rates);

                foreach($rateResponse->rates as $rateRow)
                {
                    $method = $this->_buildRate($rateRow);
                    $result->append($method);
                }
            }
        }

        return $result;
    }
    /**
     * Build Rate
     *
     * @param array $rateRow
     * @return Method
     */
    protected function _buildRate($rateRow)
    {
        $rateResultMethod = $this->rateMethodFactory->create();
        /**
         * Set carrier's method data
         */
        $rateResultMethod->setData('carrier', $this->getCarrierCode());
        $rateResultMethod->setData('carrier_title', $rateRow->carrier);
        /**
         * Displayed as shipping method
         */
        $methodTitle = $rateRow->service_name;;

        $rateResultMethod->setData('method_title', $methodTitle);
        $rateResultMethod->setData('method', $methodTitle);
        $rateResultMethod->setPrice($rateRow->price);
        $rateResultMethod->setData('cost', $rateRow->price);

        return $rateResultMethod;
    }

    public function getRates($rateRequest)
    {
        $jsonRateRequest = json_encode($rateRequest);

        try {
            $response = $this->_get($jsonRateRequest);

            return $response;
        } catch (Exception $e) {
            $this->logger->critical($e);
        }
    }

    protected function _get($jsonRateRequest) {
        $params = http_build_query(['api_key' => $this->getConfigData('api_key')]);
        $ch_url = $this->getConfigData('gateway_url') . 'rates' . '?' . $params;

        //$this->logger->debug(var_export(json_decode($jsonRateRequest), true));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $ch_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRateRequest);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonRateRequest)
            )
        );

        $resp = curl_exec($ch);
        $arr_res = json_decode($resp);

        $this->mlog($resp, 'shiphawk_rates_response.log');

        //$this->logger->debug(var_export($arr_res, true));

        curl_close($ch);
        return $arr_res;
    }

    public function getItems($request)
    {
        $items = array();

        foreach ($request->getAllItems() as $item) {

            if ($item->getProductType() != 'simple') {

                if ($option = $item->getOptionByCode('simple_product')->getProduct()) {

                    $item_weight = $item->getWeight();
                    $new_item = array(
                        'product_sku' => $item->getSku(),
                        'quantity' => $item->getQty(),
                        'value' => $option->getPrice(),
                        'length' => floatval($option->getResource()->getAttributeRawValue($option->getId(),'shiphawk_length', null)),
                        'width' => floatval($option->getResource()->getAttributeRawValue($option->getId(),'shiphawk_width', null)),
                        'height' => floatval($option->getResource()->getAttributeRawValue($option->getId(),'shiphawk_height', null)),
                        'weight' => $item_weight <= 70 ? $item_weight * 16 : $item_weight,
                        'item_type' => $item_weight <= 70 ? 'parcel' : 'handling_unit'
                    );
                    if ($item_weight > 70) {
                        $new_item['handling_unit_type'] = 'box';
                    }
                    $items[] = $new_item;
                }

            } else if ($item->getProductType() != 'configurable' && !$item->getParentItemId()) {

                    $item_weight = $item->getWeight();
                    $new_item = array(
                        'product_sku' => $item->getSku(),
                        'quantity' => $item->getQty(),
                        'value' => $item->getPrice(),
                        'length' => floatval($item->getProduct()->getResource()->getAttributeRawValue($item->getProduct()->getId(),'shiphawk_length', null)),
                        'width' => floatval($item->getProduct()->getResource()->getAttributeRawValue($item->getProduct()->getId(),'shiphawk_width', null)),
                        'height' => floatval($item->getProduct()->getResource()->getAttributeRawValue($item->getProduct()->getId(),'shiphawk_height', null)),
                        'weight' => $item_weight <= 70 ? $item_weight * 16 : $item_weight,
                        'item_type' => $item_weight <= 70 ? 'parcel' : 'handling_unit'
                    );
                    if ($item_weight > 70) {
                        $new_item['handling_unit_type'] = 'box';
                    }
                    $items[] = $new_item;
            }
        }

        return $items;
    }

    public function mlog($data, $file_mame = 'custom.log') {

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$file_mame);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info(var_export($data, true));
    }

    public function getConfigData($key) {
        return $this->scopeConfig->getValue('general/options/shiphawk_'.$key, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    private function getOrigRegionCode() {
            $origRegionId = $this->scopeConfig->getValue(Config::XML_PATH_ORIGIN_REGION_ID);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $region = $objectManager->create('Magento\Directory\Model\RegionFactory')->create();
            return $region->load($origRegionId)->getCode();
    }

    private function getOriginCountryCode($request) {
            if ($request->getOrigCountry()) {
                    $origCountry = $request->getOrigCountry();
            } else {
                    $origCountry = $this->_scopeConfig->getValue(\Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_COUNTRY_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $request->getStoreId());
            }

            return $origCountry;
    }

    private function getOriginCity($request) {
            if ($request->getOrigCity()) {
                    $origCity = $request->getOrigCity();
            } else {
                    $origCity = $this->_scopeConfig->getValue(\Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_CITY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $request->getStoreId());
            }

            return $origCity;
    }
}
