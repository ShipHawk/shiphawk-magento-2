<?php declare(strict_types=1);

namespace Shiphawk\Shipping\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;

class Shiphawk extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = 'shiphawk';

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;

    private \Shiphawk\Shipping\Model\Api\Client $shiphawkClient;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Shiphawk\Shipping\Model\Api\Client $shiphawkClient,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->shiphawkClient = $shiphawkClient;
    }

    /**
     * @inheritdoc
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        // Zip code is required, no need doing any request
        if (empty($request->getDestPostcode())) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        $shippingPrices = $this->shiphawkClient->getRates($request);

        if (!empty($shippingPrices)) {
            foreach ($shippingPrices as $rate) {
                $rateResult = $this->createResultMethod(
                    $this->getFinalPriceWithHandlingFee($rate['price']),
                    $rate['title']
                );
                $result->append($rateResult);
            }
        } else {
            $rates = $this->createError();
            $result->append($rates);
        }
        // We're adding phpstan-ignore because it's not possible returning type as declared in CarrierInterface
        return $result; // @phpstan-ignore-line
    }

    /**
     * @inheritdoc
     */
    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => __($this->getConfigData('title'))];
    }

    /**
     * @inheritdoc
     */
    public function isZipCodeRequired($countryId = null)
    {
        return true;
    }

    /**
     * Creates result method
     *
     * @param float $shippingPrice
     * @param string $title
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     */
    private function createResultMethod(
        float $shippingPrice,
        string $title
    ) : \Magento\Quote\Model\Quote\Address\RateResult\Method {
        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod(strtolower(str_replace(' ', '-', $title)));
        $method->setMethodTitle($title);

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        return $method;
    }

    /**
     * Create result error
     *
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Error
     */
    private function createError() : \Magento\Quote\Model\Quote\Address\RateResult\Error
    {
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setErrorMessage($this->getConfigData('specificerrmsg'));
        return $error;
    }
}
