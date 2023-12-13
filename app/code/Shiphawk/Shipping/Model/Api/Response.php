<?php declare(strict_types=1);

namespace Shiphawk\Shipping\Model\Api;

use Magento\Framework\Exception\LocalizedException;

class Response
{
    private const TITLE_FIELD_NAME = 'rate_display_name';
    private const PRICE_FIELD_NAME = 'price';
    private \Magento\Framework\Serialize\Serializer\Json $json;

    public function __construct(
        \Magento\Framework\Serialize\Serializer\Json $json
    ) {
        $this->json = $json;
    }

    /**
     * Parsing response to get a rates array in a view [['title' => string, 'price' => float], ... ]
     *
     * @param string $response
     * @return array
     * @throws LocalizedException
     */
    public function parseResponse(string $response) : array
    {
        $rates = [];
        $responseArray = $this->json->unserialize($response);
        if (!isset($responseArray['rates']) || empty($responseArray['rates'])) {
            if (isset($responseArray['error'])) {
                $error = __($responseArray['error']);
            } else {
                $error = __('No rates in response');
            }
            throw new LocalizedException($error);
        }
        foreach ($responseArray['rates'] as $rate) {
            if (isset($rate[self::TITLE_FIELD_NAME]) && isset($rate[self::PRICE_FIELD_NAME])) {
                $rates[] = [
                    'title' => $rate[self::TITLE_FIELD_NAME],
                    'price' => (float)$rate[self::PRICE_FIELD_NAME],
                ];
            }
        }
        return $rates;
    }
}
