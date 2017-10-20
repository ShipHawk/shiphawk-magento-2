<?php
namespace Shiphawk\Order\Model\Cron;

class ProcessOrderForLast14Days
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Shiphawk\Order\Observer\SendOrder $sendOrder,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->sendOrder = $sendOrder;


    }

    public function execute()
    {
        $active = $this->scopeConfig->getValue('general/options/shiphawk_active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        if (!$active) {
            return;
        }

        $orders =  $this->_orderCollectionFactory->create()->addAttributeToSelect('*')->addAttributeToFilter(
            'created_at',
            ['gt' => date("Y-m-d", strtotime('now - 14 days'))]
        );

        foreach ($orders as $order) {
            $this->sendOrder->pushOrder($order);
        }

    }
}
