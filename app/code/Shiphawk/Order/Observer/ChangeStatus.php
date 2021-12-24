<?php

namespace Shiphawk\Order\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;


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
                'source_system' => 'magento',
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

        $api_key = $this->scopeConfig->getValue('general/options/shiphawk_api_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $gateway_url = $this->scopeConfig->getValue('general/options/shiphawk_gateway_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $params = http_build_query(['api_key' => $api_key]);
        $ch_url = $gateway_url . 'orders/' . $order->getIncrementId() . '/cancelled' . '?' . $params;

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