<?php declare(strict_types=1);

namespace Shiphawk\Shipping\Model\Api;

use Magento\Quote\Model\Quote\Address\RateRequest;

class Request
{
    private const RATES_URL_PART = '/api/v4/rates';
    private const WEIGHT_LIMIT = 70;
    private const WEIGHT_MULTIPLIER = 16;
    private const DIMENSION_DEFAULT_VALUE = 10;

    private \Shiphawk\Shipping\Model\Config\Provider $config;
    private \Magento\Framework\Serialize\Serializer\Json $json;
    private \Magento\Directory\Model\RegionFactory $regionFactory;
    
    public function __construct(
        \Shiphawk\Shipping\Model\Config\Provider $config,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Magento\Directory\Model\RegionFactory $regionFactory
    ) {
        $this->config = $config;
        $this->json = $json;
        $this->regionFactory = $regionFactory;
    }

    public function buildRequestUrl() : string
    {
        $gatewayUrl = $this->config->getGatewayUrl();
        return rtrim($gatewayUrl) . self::RATES_URL_PART;
    }

    public function buildRequestBody(RateRequest $rateRequest) : string
    {
        $body = [
            "items" => $this->getItems($rateRequest),
            "origin_address" => $this->getOriginAddress($rateRequest),
            "destination_address" => $this->getDestinationAddress($rateRequest),
            "apply_rules" => "true",
            "rate_source" => "magento2"
        ];
        return $this->json->serialize($body);
    }

    private function getOriginAddress(RateRequest $rateRequest) : array
    {
        if ($rateRequest->getOrigRegionId()) {
            $regionCode = $rateRequest->getOrigRegionId();
        } else {
            $region = $this->regionFactory->create()->load( // @phpstan-ignore-line
                $this->config->getOriginState()
            );
            $regionCode = $region->getCode();
        }

        return [
            "state" => $regionCode,
            "country" => $rateRequest->getOrigCountryId() ?? $this->config->getOriginCountry(),
            "city" => $rateRequest->getOrigCity() ?? $this->config->getOriginCity(),
            "zip" => $rateRequest->getOrigPostcode() ?? $this->config->getOriginZip(),
        ];
    }

    private function getDestinationAddress(RateRequest $rateRequest) : array
    {
        return [
            "state" => $rateRequest->getDestRegionCode(),
            "country" => $rateRequest->getDestCountryId(),
            "city" => $rateRequest->getDestCity(),
            "zip" => $rateRequest->getDestPostcode(),
            "street1" => $rateRequest->getDestStreet(),
            "is_residential" => "true",
        ];
    }

    private function getItems(RateRequest $rateRequest) : array
    {
        $items = [];
        $parentPrices = [];
        foreach ($rateRequest->getAllItems() as $item) {
            if ($item->getProduct()->getTypeId() !== \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE) {
                $parentPrices[$item->getItemId()] = $item->getPrice();
                continue;
            }
            $price = $item->getParentItemId() ?
                (array_key_exists($item->getParentItemId(), $parentPrices) ?
                    $parentPrices[$item->getParentItemId()] :
                    $item->getPrice()
                ) :
                $item->getPrice();
            $itemArray = [
                'product_sku' => $item->getSku(),
                'quantity' => $item->getQty(),
                'value' => $price,
                'length' => $this->getDimensionItemValue($item, 'shiphawk_length'),
                'width' => $this->getDimensionItemValue($item, 'shiphawk_width'),
                'height' => $this->getDimensionItemValue($item, 'shiphawk_height'),
                'weight' => $item->getWeight() <= self::WEIGHT_LIMIT ?
                    $item->getWeight() * self::WEIGHT_MULTIPLIER
                    : $item->getWeight(),
                'item_type' => $item->getWeight() <= self::WEIGHT_LIMIT ? 'parcel' : 'handling_unit'
            ];
            if ($item->getWeight() > self::WEIGHT_LIMIT) {
                $itemArray['handling_unit_type'] = 'box';
            }
            $items[] = $itemArray;
        }
        return $items;
    }

    private function getDimensionItemValue(
        \Magento\Quote\Api\Data\CartItemInterface $item,
        string $dimensionAttribute
    ) : float {
        $attributeValue = $item->getProduct()->getData($dimensionAttribute); // @phpstan-ignore-line
        return (float)$attributeValue > 0.01 ? (float)$attributeValue : self::DIMENSION_DEFAULT_VALUE;
    }
}
