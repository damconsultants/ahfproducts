<?php

namespace DamConsultants\Ahfproducts\Model;

use DamConsultants\Ahfproducts\Api\BynderMetapropertyInterface;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class BynderMetaproperty implements BynderMetapropertyInterface
{
    /**
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * Product Sku.
     * @param \DamConsultants\Ahfproducts\Helper\Data $DataHelper
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     */
    public function __construct(
        \DamConsultants\Ahfproducts\Helper\Data $DataHelper,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory
    ) {
        $this->datahelper = $DataHelper;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
    }
    public function getMetaProperty()
    {
		$collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);
		return $meta_properties;
	}
	 /**
     * Get Meta Properties Collection
     *
     * @param array $collection
     * @return array $response_array
     */
    public function getMetaPropertiesCollection($collection)
    {
        $collection_data_value = [];
        $collection_data_slug_val = [];
        if (count($collection) >= 1) {
            foreach ($collection as $key => $collection_value) {
                $collection_data_value[] = [
                    'id' => $collection_value['id'],
                    'property_name' => $collection_value['property_name'],
                    'property_id' => $collection_value['property_id'],
                    'magento_attribute' => $collection_value['magento_attribute'],
                    'attribute_id' => $collection_value['attribute_id'],
                    'bynder_property_slug' => $collection_value['bynder_property_slug'],
                    'system_slug' => $collection_value['system_slug'],
                    'system_name' => $collection_value['system_name']
                ];
                $collection_data_slug_val[$collection_value['system_slug']] = [
                    'bynder_property_slug' => $collection_value['bynder_property_slug'],
                ];
            }
        }
        $response_array = [
            "collection_data_value" => $collection_data_value,
            "collection_data_slug_val" => $collection_data_slug_val
        ];
        return $response_array;
    }
}