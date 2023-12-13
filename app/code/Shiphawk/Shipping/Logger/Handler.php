<?php declare(strict_types=1);

namespace Shiphawk\Shipping\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    protected $loggerType = \Monolog\Logger::INFO;

    protected $fileName = '/var/log/shiphawk.log';
}
