<?php

namespace Shiphawk\Order\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../ShGatewayBuilder.php';

class ChangeStatus implements ObserverInterface
{
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Catalog\Model\Session $catalogSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    )
    {
        $this->_request = $request;
        $this->catalogSession = $catalogSession;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;

        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $jsonOrderRequest = json_encode(
            array(
                'source_system' => 'magento2',
                'source_system_id' => $order->getEntityId(),
                'source_system_processed_at' => '',
                'cancelled_at' => $order->getUpdatedAt(),
                'status' => $this->map($order->getStatus()),
            )
        );

        try {
            $response =  $this->_push($jsonOrderRequest, $order);
            $this->mlog(var_export($response, true), 'response_update.log');

        } catch (Exception $e) {
            $this->mlog($e->getMessage(), 'error.log');
        }

    }

    protected function _push($jsonOrderRequest, $order) {
        $apiKey = $this->scopeConfig->getValue('general/options/shiphawk_api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $gatewayUrl = $this->scopeConfig->getValue('general/options/shiphawk_gateway_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $ch = curl_init();
        $chUrl = \Shiphawk\ShGatewayBuilder::buildOrderUrl($gatewayUrl, $apiKey, $order);
        curl_setopt($ch, CURLOPT_URL, $chUrl);
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

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/'.$file_mame);
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info(var_export($data, true));
    }

    public function map($status)
    {
        switch ($status) {
            case 'canceled':
                return 'cancelled';
            case 'complete':
                return 'shipped';
            case 'processing':
                return 'partially_shipped';
            case 'holded':
                return 'on_hold';
            case 'pending':
                return 'new';
            default:
                return $status;
        }
    }


}
