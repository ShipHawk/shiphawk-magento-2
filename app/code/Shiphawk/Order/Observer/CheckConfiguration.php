<?php

namespace Shiphawk\Order\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../ShGatewayBuilder.php';

class CheckConfiguration implements ObserverInterface
{
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Catalog\Model\Session $catalogSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface  $resourceConfig,
        LoggerInterface $logger
    )
    {
        $this->_request = $request;
        $this->catalogSession = $catalogSession;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;

        $this->messageManager = $messageManager;

        $this->resourceConfig = $resourceConfig;

        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $response = $this->_get();
        $this->mlog($response, 'check_conf_response.log');
        if((!$this->scopeConfig->getValue('general/store_information/name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) || (!$this->scopeConfig->getValue('general/store_information/phone',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE))) {

            $this->messageManager->addWarning('Missing information required for printing labels: Please add a store name and phone number under Configuration > General > Store Information');
        }

        if (is_null($response) || property_exists($response, 'error')) {
            $this->messageManager->addError('Unable to authenticate ShipHawk API key.');
            $this->resourceConfig->saveConfig(
                'general/options/shiphawk_active',
                '0',
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                \Magento\Store\Model\Store::DEFAULT_STORE_ID
            );

        }else{
            $this->messageManager->addSuccess('Your account is successfully linked with ShipHawk.');
        }

    }

    protected function _get() {
        $apiKey = $this->scopeConfig->getValue('general/options/shiphawk_api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $gatewayUrl = $this->scopeConfig->getValue(
            'general/options/shiphawk_gateway_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $ch = curl_init();
        $chUrl = \Shiphawk\ShGatewayBuilder::buildUserUrl($gatewayUrl, $apiKey);
        curl_setopt($ch, CURLOPT_URL, $chUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $resp = curl_exec($ch);
        $arr_res = json_decode($resp);

        curl_close($ch);
        return $arr_res;
    }

    public function mlog($data, $file_mame = 'custom.log') {

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/'.$file_mame);
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info(var_export($data, true));
    }

}