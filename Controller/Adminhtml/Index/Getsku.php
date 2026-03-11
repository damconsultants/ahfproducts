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
        $current_time = strtotime((string)date('Y-m-03'));
        $bynder_auth["last_cron_time"] = $current_time;
        $get_api_delete_details = $this->datahelper->getCheckBynderSideDeleteData($bynder_auth);
        $response = json_decode($get_api_delete_details, true);
        if (!$this->getRequest()->isAjax()) {
            $this->_forward('noroute');
            return;
        }
        $attribute_value = $this->getRequest()->getParam('select_attribute');
        $sku_limit = $this->getRequest()->getParam('sku_limit');

        $product_sku = [];
        $sku = [];
        $id = [];
        $attribute = $this->collectionFactory->create();
        $productcollection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('type_id', ['neq' => "configurable"]);
        if (count($attribute) > 0) {
            foreach ($attribute as $value) {
                $id[] = $value['attribute_set_id'];
            }
        }
        $array = array_unique($id);
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
        /*  IMAGE & VIDEO == 1 , IMAGE == 2 , VIDEO == 3 */
        $final = array_merge($image_id, $doc_id);
        $ids = array_unique($final);
        if (!empty($attribute_value)) {
            if ($attribute_value == "image") {
                $productcollection->addAttributeToFilter('attribute_set_id', $image_id);
                
                foreach ($productcollection as $product) {
                    if (!empty($product['bynder_multi_img'])) {
                        if ($product['bynder_isMain'] != "2" && $product['bynder_isMain'] != "1") {
                            $product_sku[] = $product->getSku();
                        }
                    } else {
                        $product_sku[] = $product->getSku();
                    }
                }
            } elseif ($attribute_value == "video") {
                $productcollection->addAttributeToFilter('attribute_set_id', $image_id);
                
                foreach ($productcollection as $product) {
                    if (!empty($product['bynder_multi_img'])) {
                        if ($product['bynder_isMain'] != "3" && $product['bynder_isMain'] != "1") {
                            $product_sku[] = $product->getSku();
                        }
                    } else {
                        $product_sku[] = $product->getSku();
                    }
                    
                }
            } elseif ($attribute_value == "document") {

                $productcollection->addAttributeToFilter('attribute_set_id', $doc_id)
                    ->addAttributeToFilter(
                        [
                            ['attribute' => 'bynder_document', 'null' => true]
                        ]
                    );
                foreach ($productcollection as $product) {
                    $product_sku[] = $product->getSku();
                }
            } elseif ($attribute_value == "all_attribute") {
				// Filter products by 'attribute_set_id' with $image_id
				$productcollection->addAttributeToFilter('attribute_set_id', $image_id);

				foreach ($productcollection as $product) {
					// Check for 'bynder_multi_img' and filter based on 'bynder_isMain'
					if (!empty($product['bynder_multi_img'])) {
						if ($product['bynder_isMain'] != '1') {
							$product_sku[] = $product->getSku();
						}
					} else {
						$product_sku[] = $product->getSku();
					}
				}

				// Add additional filter for 'attribute_set_id' with $doc_id and 'bynder_document' being null
				$productcollection->clear()
					->addAttributeToFilter('attribute_set_id', $doc_id)
					->addAttributeToFilter('bynder_document', ['null' => true]);

				foreach ($productcollection as $product) {
					$product_sku[] = $product->getSku();
				}
			}
        } else {

            $productcollection->addAttributeToFilter('attribute_set_id', $ids)
                ->addAttributeToFilter(
                    [
                        ['attribute' => 'bynder_multi_img', 'null' => true],
                        ['attribute' => 'bynder_document', 'null' => true]
                    ]
                );
            foreach ($productcollection as $product) {
                $product_sku[] = $product->getSku();
            }
        }
        $sku = array_unique($product_sku);
        $fetch_sku = [];
        $i = 1;
        foreach ($sku as $newsku) {
            $fetch_sku[] = $newsku;
            if ($sku_limit != 0) {
                if ($sku_limit == $i) {
                    break;
                }
            }
            $i++;
        }
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
}
