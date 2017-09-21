<?php

namespace Shiphawk\Order\Model\System\Configuration\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GatewayMode implements OptionSourceInterface
{
    /**
     * Return gateway mode
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'https://shiphawk.com/api/v4/', 'label' => __('Production')],
            ['value' => 'https://sandbox.shiphawk.com/api/v4/', 'label' => __('Sandbox')],
        ];
    }
}