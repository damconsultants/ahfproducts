<?php

namespace DamConsultants\Ahfproducts\Block\Adminhtml\Metaproperty;

use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\DefaultMetaPropertyCollectionFactory;

class Index extends \Magento\Backend\Block\Template
{
    /**
     * @var Database
     */
    protected $_helperdata;
    /**
     * @var Database
     */
    protected $_metaProperty;
    /**
     * @var Database
     */
    protected $_metaPropertyCollectionFactory;
    /**
     * @var Database
     */
    protected $_default_metaProperty_collection;
    /**
     * @var Database
     */
    protected $_storeManager;

    /**
     * Metaproperty
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \DamConsultants\Ahfproducts\Helper\Data $helperdata
     * @param \DamConsultants\Ahfproducts\Model\MetaPropertyFactory $metaProperty
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param DefaultMetaPropertyCollectionFactory $DefaultMetaPropertyCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $data
     */

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \DamConsultants\Ahfproducts\Helper\Data $helperdata,
        \DamConsultants\Ahfproducts\Model\MetaPropertyFactory $metaProperty,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        DefaultMetaPropertyCollectionFactory $DefaultMetaPropertyCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_helperdata = $helperdata;
        $this->_metaProperty = $metaProperty;
        $this->_metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->_default_metaProperty_collection = $DefaultMetaPropertyCollectionFactory;
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * SubmitUrl.
     *
     * @return $this
     */
    public function getSubmitUrl()
    {
        return $this->getUrl("bynder/index/submit");
    }
	/**
     * SubmitUrl.
     *
     * @return $this
     */
    public function getSynmetaProperty()
    {
        return $this->getUrl("bynder/index/synmetapropertys");
    }
    /**
     * Get MetaData.
     *
     * @return $this
     */
    public function getMetaData()
    {
        $response_data = [];
        $attribute_array = [];
        $defaultmetaPropertycollection = $this->_default_metaProperty_collection->create();
        $defaultmetaPropertycollection_data = $defaultmetaPropertycollection->getData();
        if (count($defaultmetaPropertycollection_data) > 0) {
            foreach ($defaultmetaPropertycollection_data as $meta_val) {
                $attribute_array[$meta_val['bynder_property_slug']] = $meta_val['property_name'];
            }
        }
        $collection = $this->_metaPropertyCollectionFactory->create();
        if (count($attribute_array) > 0) {
            $response_data['metadata'] = $attribute_array;
        } else {
            $response_data['metadata'] = [];
        }
        $collection = $this->_metaPropertyCollectionFactory->create();
        $colletion_get_data = $collection->getData();
        $properties_details = [];
        if (count($colletion_get_data) > 0) {
            foreach ($colletion_get_data as $metacollection) {
                $properties_details[$metacollection['system_slug']] = [
                    "id" => $metacollection['id'],
                    "property_name" => $metacollection['property_name'],
                    "property_id" => $metacollection['property_id'],
                    "magento_attribute" => $metacollection['magento_attribute'],
                    "attribute_id" => $metacollection['attribute_id'],
                    "bynder_property_slug" => $metacollection['bynder_property_slug'],
                    "system_slug" => $metacollection['system_slug'],
                    "system_name" => $metacollection['system_name'],
                ];
            }

            $response_data['sku_selected'] = isset($properties_details["sku"]["bynder_property_slug"])
            ? $properties_details["sku"]["bynder_property_slug"]
            : '0';
            $response_data['image_role_selected']= isset($properties_details["image_role"]["bynder_property_slug"])
            ? $properties_details["image_role"]["bynder_property_slug"] 
            : '0';
            $response_data['image_alt_text']= isset($properties_details["alt_text"]["bynder_property_slug"])
            ? $properties_details["alt_text"]["bynder_property_slug"]
            : '0';
			$response_data['image_order']= isset($properties_details["image_order"]["bynder_property_slug"])
            ? $properties_details["image_order"]["bynder_property_slug"] 
            : '0';
            $response_data['sub_type']= isset($properties_details["sub_type"]["bynder_property_slug"])
            ? $properties_details["sub_type"]["bynder_property_slug"] 
            : '0';
        } else {
            $response_data['sku_selected'] = '0';
            $response_data['image_role_selected'] = '0';
            $response_data['image_alt_text'] = '0';
			$response_data['image_order'] =  '0';
            $response_data['sub_type'] =  '0';
        }
        return $response_data;
    }
    /**
     * Get Select Property.
     *
     * @return $this
     */
    public function getSelectProperites()
    {
        $collection = $this->_metaPropertyCollectionFactory->create();
        $colletion_get_data = $collection->getData();
        $select_properites_array = [];
        if (count($colletion_get_data) > 0) {
            foreach ($colletion_get_data as $key => $value) {
                $select_properites_array[] = [
                    'property_name' => $value['property_name'],
                    'bynder_property_slug' => $value['bynder_property_slug']
                ];
            }
        } else {
            $select_properites_array = [
                'property_name' => 0,
                'bynder_property_slug' => 0
            ];
        }
        return $select_properites_array;
    }
    /**
     * Get main url.
     *
     * @return string
     */
    public function getMainUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }
}
