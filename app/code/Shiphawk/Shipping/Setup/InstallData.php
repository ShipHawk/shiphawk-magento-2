<?php

namespace Shiphawk\Shipping\Setup;

use Magento\Catalog\Model\Product;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Quote\Setup\QuoteSetupFactory;
use Magento\Sales\Setup\SalesSetupFactory;

class InstallData implements InstallDataInterface
{
    /**
     * @var EavSetupFactory
     */
    protected $eavSetupFactory;

    /**
     * @var QuoteSetupFactory
     */
    protected $quoteSetupFactory;

    /**
     * @var SalesSetupFactory
     */
    protected $salesSetupFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    protected $setup;

    /**
     * @param EavSetupFactory $eavSetupFactory
     * @param QuoteSetupFactory $quoteSetupFactory
     * @param SalesSetupFactory $salesSetupFactory
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        QuoteSetupFactory $quoteSetupFactory,
        SalesSetupFactory $salesSetupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
        $this->salesSetupFactory = $salesSetupFactory;
    }

    protected function getLength()
    {
        return [
            'label' => 'Length',
            'input' => 'text',
            'type' => 'decimal',
            'frontend_class' => 'validate-number',
        ];
    }

    protected function getWidth()
    {
        return [
            'label' => 'Width',
            'input' => 'text',
            'type' => 'decimal',
            'frontend_class' => 'validate-number',
        ];
    }

    protected function getHeight()
    {
        return [
            'label' => 'Height',
            'input' => 'text',
            'type' => 'decimal',
            'frontend_class' => 'validate-number',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->setup = $setup;
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $groupName = 'Shiphawk Attributes';

        $attributes = [
            'shiphawk_length' => $this->getLength(),
            'shiphawk_width' => $this->getWidth(),
            'shiphawk_height' => $this->getHeight()
        ];

        $sortOrder = 0;
        foreach ($attributes as $code => $attribute) {
            $sortOrder += 10;
            $eavSetup->addAttribute(
                Product::ENTITY,
                $code,
                array_merge(
                    [
                        'attribute_set' => $eavSetup->getDefaultAttributeSetId(Product::ENTITY),
                        'group' => $groupName,
                        'visible' => true,
                        'sort_order' => $sortOrder,
                        'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
                        'apply_to' => 'simple,bundle,configurable',
                        'searchable' => false,
                        'comparable' => false,
                        'filterable' => false,
                        'required' => false,
                        'visible_in_advanced_search' => false,
                        'used_in_product_listing' => false,
                        'used_for_sort_by' => false,
                        'is_used_in_grid' => false,
                        'is_visible_in_grid' => false,
                        'is_filterable_in_grid' => false,
                    ],
                    $attribute
                )
            );
        }

        $eavSetup->updateAttributeGroup(
            Product::ENTITY,
            $eavSetup->getDefaultAttributeSetId(Product::ENTITY),
            $groupName,
            'attribute_group_code',
            'shiphawk_attributes'
        );
        $eavSetup->updateAttributeGroup(
            Product::ENTITY,
            $eavSetup->getDefaultAttributeSetId(Product::ENTITY),
            $groupName,
            'tab_group_code',
            'advanced'
        );
    }
}
