<?php

namespace Shiphawk\Order\Model\System\Configuration\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Ordersync implements OptionSourceInterface
{
    /**
     * Return gateway mode
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Send orders to ShipHawk')],
            ['value' => '0', 'label' => __('Do not send orders to ShipHawk')],
        ];
    }
}
