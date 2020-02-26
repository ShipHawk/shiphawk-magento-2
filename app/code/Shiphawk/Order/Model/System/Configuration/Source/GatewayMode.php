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
            ['value' => 'https://qa.shiphawk.com/api/v4/', 'label' => __('QA')],
            ['value' => 'https://rc.shiphawk.com/api/v4/', 'label' => __('RC')],
            ['value' => 'https://stage.shiphawk.com/api/v4/', 'label' => __('STAGE')],
            ['value' => 'https://uat.shiphawk.com/api/v4/', 'label' => __('UAT')],
            // ['value' => 'host.docker.internal:3000/api/v4/', 'label' => __('Localhost')],
        ];
    }
}
