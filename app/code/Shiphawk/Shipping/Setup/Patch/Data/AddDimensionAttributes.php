<?php declare(strict_types=1);

namespace Shiphawk\Shipping\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class AddDimensionAttributes implements DataPatchInterface
{
    private const GROUP_NAME = 'Shiphawk';
    private \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory;
    private \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup;
    private \Magento\Eav\Api\AttributeGroupRepositoryInterface $attributeGroupRepository;
    private \Magento\Eav\Api\Data\AttributeGroupInterfaceFactory $attributeGroupFactory;

    private $attributes = [
        'shiphawk_length',
        'shiphawk_width',
        'shiphawk_height'
    ];

    private \Magento\Eav\Setup\EavSetup $eavSetup;

    private array $attributeSets = [];

    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Eav\Api\AttributeGroupRepositoryInterface $attributeGroupRepository,
        \Magento\Eav\Api\Data\AttributeGroupInterfaceFactory $attributeGroupFactory
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->attributeGroupRepository = $attributeGroupRepository;
        $this->attributeGroupFactory = $attributeGroupFactory;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $this->createAttributeGroup();
        foreach ($this->attributes as $attributeCode) {
            $this->addAttribute($attributeCode);
            $this->addAttributeToSets($attributeCode);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    private function getAttributeSets() : array
    {
        if (empty($this->attributeSets)) {
            $select = $this->moduleDataSetup->getConnection()
                ->select()
                ->from(
                    $this->moduleDataSetup->getTable('eav_attribute_set'),
                    ['attribute_set_id']
                )->where(
                    'entity_type_id = ?',
                    $this->eavSetup->getEntityTypeId(Product::ENTITY)
                );

            $this->attributeSets = $this->moduleDataSetup->getConnection()
                ->fetchCol($select);
        }
        return $this->attributeSets;
    }

    private function createAttributeGroup() : self
    {
        foreach ($this->getAttributeSets() as $setId) {
            $attributeGroup = $this->attributeGroupFactory->create();
            $attributeGroup->setAttributeSetId($setId);
            $attributeGroup->setAttributeGroupName(self::GROUP_NAME);
            $this->attributeGroupRepository->save($attributeGroup);
        }
        return $this;
    }

    private function addAttributeToSets(string $attributeCode) : self
    {
        $entityTypeId = $this->eavSetup->getEntityTypeId(Product::ENTITY);
        foreach ($this->getAttributeSets() as $setId) {
            $this->eavSetup->addAttributeToSet(
                $entityTypeId,
                $setId,
                self::GROUP_NAME,
                $attributeCode,
                10
            );
        }
        return $this;
    }

    private function addAttribute($attributeCode) : self
    {
        $this->eavSetup->addAttribute(
            Product::ENTITY,
            $attributeCode,
            [
                'type' => 'decimal',
                'label' => ucfirst(str_replace('shiphawk_', '', $attributeCode)),
                'input' => 'text',
                'frontend' => '',
                'required' => false,
                'note' => '',
                'class' => '',
                'backend' => '',
                'sort_order' => '10',
                'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
                'default' => null,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => true,
                'unique' => false,
                'apply_to' => 'simple,bundle,configurable',
                'used_in_product_listing' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'used_for_sort_by' => false,
                'source' => '',
                'frontend_class' => 'validate-number',
            ]
        );
        return $this;
    }
}
