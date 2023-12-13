<?php declare(strict_types=1);

namespace Shiphawk\Shipping\Model\Config;

use Magento\Shipping\Model\Config;

class Provider
{
    private const CONFIG_PATH_API_KEY = 'carriers/shiphawk/api_key';
    private const CONFIG_PATH_GATEWAY = 'carriers/shiphawk/gateway_url';
    private const CONFIG_PATH_DEBUG = 'carriers/shiphawk/debug';

    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function getApiKey() : string
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_API_KEY);
    }

    public function getGatewayUrl() : string
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_GATEWAY);
    }

    public function getOriginCity() : string
    {
        return (string)$this->scopeConfig->getValue(Config::XML_PATH_ORIGIN_CITY);
    }

    public function getOriginState() : string
    {
        return (string)$this->scopeConfig->getValue(Config::XML_PATH_ORIGIN_REGION_ID);
    }

    public function getOriginZip() : string
    {
        return (string)$this->scopeConfig->getValue(Config::XML_PATH_ORIGIN_POSTCODE);
    }

    public function getOriginCountry() : string
    {
        return (string)$this->scopeConfig->getValue(Config::XML_PATH_ORIGIN_COUNTRY_ID);
    }

    public function isDebugEnabled() : bool
    {
        return (bool)$this->scopeConfig->getValue(self::CONFIG_PATH_DEBUG);
    }
}
