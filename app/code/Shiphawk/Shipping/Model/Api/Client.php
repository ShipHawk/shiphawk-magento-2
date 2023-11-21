<?php declare(strict_types=1);

namespace Shiphawk\Shipping\Model\Api;

use Magento\Quote\Model\Quote\Address\RateRequest;

class Client
{
    private \Magento\Framework\HTTP\Client\Curl $curl;
    private \Shiphawk\Shipping\Model\Api\Request $request;
    private \Shiphawk\Shipping\Model\Api\Response $response;
    private \Shiphawk\Shipping\Model\Config\Provider $config;
    private \Shiphawk\Shipping\Logger\Logger $logger;
    private \Magento\Framework\Event\ManagerInterface $eventManager;

    public function __construct(
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Shiphawk\Shipping\Model\Api\Request $request,
        \Shiphawk\Shipping\Model\Api\Response $response,
        \Shiphawk\Shipping\Model\Config\Provider $config,
        \Shiphawk\Shipping\Logger\Logger $logger,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->curl = $curl;
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    public function getRates(RateRequest $rateRequest) : array
    {
        /** @var \Magento\Framework\HTTP\Client\Curl $client */
        $client = $this->buildClient();
        $requestUrl = $this->request->buildRequestUrl();
        $requestBody = $this->request->buildRequestBody($rateRequest);
        $this->eventManager->dispatch('shiphawk_rates_request_before', ['body' => $requestBody]);
        $this->log('Doing request to URL: ' . $requestUrl);
        $this->log('Request Body: ' . $requestBody);
        $client->post($requestUrl, $requestBody);
        $response = $client->getBody();
        $this->log('Response Body: ' . $response);
        try {
            $parsedResponse = $this->response->parseResponse($response);
        } catch (\Exception $e) {
            $this->log('There was an error parsing response: ' . $e->getMessage());
            $parsedResponse = [];
        }
        return $parsedResponse;
    }

    private function buildClient() : \Magento\Framework\HTTP\Client\Curl
    {
        $curl = $this->curl;
        $curl->addHeader('Cache-Control', 'no-cache');
        $curl->addHeader('Connection', 'keep-alive');
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('X-Api-Key', $this->config->getApiKey());
        return $curl;
    }

    private function log(string $data) : self
    {
        if ($this->config->isDebugEnabled()) {
            $this->logger->info($data);
        }
        return $this;
    }
}
