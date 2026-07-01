<?php

namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

class Getsku extends \Magento\Backend\App\Action
{
    /**
     * @var protectedattribute.
     *
     */
    protected $protectedattribute;
    /**
     * @var collectionFactory.
     *
     */
    protected $collectionFactory;
    /**
     * @var resultJsonFactory.
     *
     */
    protected $resultJsonFactory;
    /**
     * @var productAttributeManagementInterface.
     *
     */
    protected $productAttributeManagementInterface;
    /**
     * @var datahelper.
     *
     */
    protected $datahelper;
    /**
     * @var Attribute.
     *
     */
    protected $attribute;

    /**
     * Get Sku.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \DamConsultants\Ahfproducts\Helper\Data $DataHelper
     * @param \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Catalog\Api\ProductAttributeManagementInterface $productAttributeManagementInterface
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \DamConsultants\Ahfproducts\Helper\Data $DataHelper,
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Catalog\Api\ProductAttributeManagementInterface $productAttributeManagementInterface,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->attribute = $attribute;
        $this->collectionFactory = $collectionFactory;
        $this->resultJsonFactory = $jsonFactory;
        $this->productAttributeManagementInterface = $productAttributeManagementInterface;
        $this->datahelper = $DataHelper;
    }
    
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');
            return;
        }
        
        $current_time = strtotime((string)date('Y-m-03'));
        $bynder_auth["last_cron_time"] = $current_time;
        $get_api_delete_details = $this->datahelper->getCheckBynderSideDeleteData($bynder_auth);
        $response = json_decode($get_api_delete_details, true);
        
        $attribute_value = $this->getRequest()->getParam('select_attribute');
        $sku_limit = (int)$this->getRequest()->getParam('sku_limit');
        
        // Set default limit if not provided or too large
        if ($sku_limit <= 0 || $sku_limit > 5000) {
            $sku_limit = 5000; // Set a reasonable max limit
        }
        
        // Get attribute set IDs once
        $attributeSetIds = $this->getAttributeSetIds();
        
        // Get SKUs based on attribute value
        $product_skus = $this->getProductSkus($attribute_value, $attributeSetIds, $sku_limit);
        
        // Remove duplicates and limit results
        $unique_skus = array_unique($product_skus);
        $fetch_sku = array_slice($unique_skus, 0, $sku_limit);
        
        if (count($fetch_sku) > 0) {
            $status = 1;
            $data_sku = implode(",", $fetch_sku);
        } else {
            $status = 0;
            $data_sku = "There is not any empty Bynder Data in product";
        }
        
        $result = $this->resultJsonFactory->create();
        return $result->setData(['status' => $status, 'message' => $data_sku]);
    }
    
    /**
     * Get Attribute Set IDs
     *
     * @return array
     */
    private function getAttributeSetIds()
    {
        $image_id = [];
        $doc_id = [];
        
        // Get all attribute sets efficiently
        $attributeCollection = $this->collectionFactory->create();
        $attributeSetIds = [];
        
        // Get unique attribute set IDs from products (more efficient than loading all products)
        $productCollection = $this->collectionFactory->create();
        $productCollection->addAttributeToSelect('attribute_set_id')
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['neq' => "configurable"]);
        
        // Use distinct to get unique attribute set IDs
        $productCollection->getSelect()->group('e.attribute_set_id');
        
        foreach ($productCollection as $product) {
            $attributeSetId = $product->getAttributeSetId();
            if ($attributeSetId) {
                $attributeSetIds[] = $attributeSetId;
            }
        }
        
        $array = array_unique($attributeSetIds);
        
        foreach ($array as $ids) {
            $productAttributes = $this->productAttributeManagementInterface->getAttributes($ids);
            
            foreach ($productAttributes as $atttr) {
                if ($atttr->getAttributeCode() == "bynder_multi_img") {
                    $image_id[] = $atttr->getAttributeSetId();
                } elseif ($atttr->getAttributeCode() == "bynder_document") {
                    $doc_id[] = $atttr->getAttributeSetId();
                }
            }
        }
        
        return [
            'image_id' => array_unique($image_id),
            'doc_id' => array_unique($doc_id),
            'all_ids' => array_unique(array_merge($image_id, $doc_id))
        ];
    }
    
    /**
     * Get Product SKUs based on attribute value
     *
     * @param string $attribute_value
     * @param array $attributeSetIds
     * @param int $limit
     * @return array
     */
    private function getProductSkus($attribute_value, $attributeSetIds, $limit)
    {
        $product_skus = [];
        
        if (!empty($attribute_value)) {
            switch ($attribute_value) {
                case "image":
                    $product_skus = $this->getImageSkus($attributeSetIds['image_id'], $limit);
                    break;
                    
                case "video":
                    $product_skus = $this->getVideoSkus($attributeSetIds['image_id'], $limit);
                    break;
                    
                case "document":
                    $product_skus = $this->getDocumentSkus($attributeSetIds['doc_id'], $limit);
                    break;
                    
                case "all_attribute":
                    $product_skus = $this->getAllAttributeSkus($attributeSetIds, $limit);
                    break;
            }
        } else {
            $product_skus = $this->getEmptyAttributeSkus($attributeSetIds['all_ids'], $limit);
        }
        
        return $product_skus;
    }
    
    /**
     * Get SKUs for image attribute
     *
     * @param array $attributeSetIds
     * @param int $limit
     * @return array
     */
    private function getImageSkus($attributeSetIds, $limit)
    {
        $skus = [];
        
        if (empty($attributeSetIds)) {
            return $skus;
        }
        
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['sku', 'bynder_multi_img', 'bynder_isMain'])
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['neq' => "configurable"])
            ->addAttributeToFilter('attribute_set_id', ['in' => $attributeSetIds])
            ->setPageSize($limit)
            ->setCurPage(1);
        
        foreach ($collection as $product) {
            $bynderMultiImg = $product->getData('bynder_multi_img');
            $bynderIsMain = $product->getData('bynder_isMain');
            
            if (!empty($bynderMultiImg)) {
                if ($bynderIsMain != "2" && $bynderIsMain != "1") {
                    $skus[] = $product->getSku();
                }
            } else {
                $skus[] = $product->getSku();
            }
        }
        
        return $skus;
    }
    
    /**
     * Get SKUs for video attribute
     *
     * @param array $attributeSetIds
     * @param int $limit
     * @return array
     */
    private function getVideoSkus($attributeSetIds, $limit)
    {
        $skus = [];
        
        if (empty($attributeSetIds)) {
            return $skus;
        }
        
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['sku', 'bynder_multi_img', 'bynder_isMain'])
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['neq' => "configurable"])
            ->addAttributeToFilter('attribute_set_id', ['in' => $attributeSetIds])
            ->setPageSize($limit)
            ->setCurPage(1);
        
        foreach ($collection as $product) {
            $bynderMultiImg = $product->getData('bynder_multi_img');
            $bynderIsMain = $product->getData('bynder_isMain');
            
            if (!empty($bynderMultiImg)) {
                if ($bynderIsMain != "3" && $bynderIsMain != "1") {
                    $skus[] = $product->getSku();
                }
            } else {
                $skus[] = $product->getSku();
            }
        }
        
        return $skus;
    }
    
    /**
     * Get SKUs for document attribute
     *
     * @param array $attributeSetIds
     * @param int $limit
     * @return array
     */
    private function getDocumentSkus($attributeSetIds, $limit)
    {
        $skus = [];
        
        if (empty($attributeSetIds)) {
            return $skus;
        }
        
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['sku'])
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['neq' => "configurable"])
            ->addAttributeToFilter('attribute_set_id', ['in' => $attributeSetIds])
            ->addAttributeToFilter('bynder_document', ['null' => true])
            ->setPageSize($limit)
            ->setCurPage(1);
        
        foreach ($collection as $product) {
            $skus[] = $product->getSku();
        }
        
        return $skus;
    }
    
    /**
     * Get SKUs for all attributes
     *
     * @param array $attributeSetIds
     * @param int $limit
     * @return array
     */
    private function getAllAttributeSkus($attributeSetIds, $limit)
    {
        $skus = [];
        
        // Get image SKUs
        $imageSkus = $this->getImageSkus($attributeSetIds['image_id'], $limit);
        $skus = array_merge($skus, $imageSkus);
        
        // Get document SKUs
        $docSkus = $this->getDocumentSkus($attributeSetIds['doc_id'], $limit);
        $skus = array_merge($skus, $docSkus);
        
        return $skus;
    }
    
    /**
     * Get SKUs with empty attributes
     *
     * @param array $attributeSetIds
     * @param int $limit
     * @return array
     */
    private function getEmptyAttributeSkus($attributeSetIds, $limit)
    {
        $skus = [];
        
        if (empty($attributeSetIds)) {
            return $skus;
        }
        
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['sku'])
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['neq' => "configurable"])
            ->addAttributeToFilter('attribute_set_id', ['in' => $attributeSetIds])
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_multi_img', 'null' => true],
                    ['attribute' => 'bynder_document', 'null' => true]
                ]
            )
            ->setPageSize($limit)
            ->setCurPage(1);
        
        foreach ($collection as $product) {
            $skus[] = $product->getSku();
        }
        
        return $skus;
    }
}