<?php

namespace DamConsultants\Ahfproducts\Cron;

use Exception;
use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\ApiBynderMediaTableCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;

class ScriptRunOneTimeForAddMediaIdinTable
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var $bynderMediaTable
     */
    protected $bynderMediaTable;
    /**
     * @var $bynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $action
     */
    protected $action;
    /**
     * @var $_byndersycData
     */
    protected $_byndersycData;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $configWriter
     */
    protected $configWriter;
    /**
     * @var $resouce
     */
    protected $resouce;
    /**
     * @var $collectionFactory
     */
    protected $collectionFactory;
    /**
     * @var $ApiBynderMediaTable
     */
    protected $ApiBynderMediaTable;
    /**
     * @var $ApiBynderMediaTableCollection
     */
    protected $ApiBynderMediaTableCollection;
    /**
     * Featch Null Data To Magento
     * @param LoggerInterface $logger
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Ahfproducts\Helper\Data $DataHelper
     * @param \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $byndersycData
     * @param \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param \DamConsultants\Ahfproducts\Model\ApiBynderMediaTableFactory $ApiBynderMediaTable
     * @param ApiBynderMediaTableCollectionFactory $ApiBynderMediaTableCollection
     * @param Action $action
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     * @param \Magento\Framework\App\ResourceConnection $resouce
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Ahfproducts\Helper\Data $DataHelper,
        \DamConsultants\Ahfproducts\Model\BynderDeleteDataFactory $byndersycData,
        \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        \DamConsultants\Ahfproducts\Model\ApiBynderMediaTableFactory $ApiBynderMediaTable,
        ApiBynderMediaTableCollectionFactory $ApiBynderMediaTableCollection,
        Action $action,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\App\ResourceConnection $resouce
    ) {
        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->_byndersycData = $byndersycData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->ApiBynderMediaTable = $ApiBynderMediaTable;
        $this->ApiBynderMediaTableCollection = $ApiBynderMediaTableCollection;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->configWriter = $configWriter;
        $this->resouce = $resouce;
    }
    /**
     * Execute
     *
     * @return boolean
     */
    public function execute()
    {
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $product_collection = $this->collectionFactory->create();
            $product_collection->getSelect()->limit(500);
            $product_collection->addAttributeToSelect('*')
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_multi_img', 'notnull' => true]
                ]
            )
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_delete_cron', 'null' => true]
                ]
            )
            ->load();
            $productSku_array = [];
            foreach ($product_collection->getData() as $product) {
                $productSku_array[] = $product['sku'];
            }
            foreach ($productSku_array as $sku) {
                $_product = $this->_productRepository->get($sku);
                $product_ids = $_product->getId();
                $image_value = $_product->getBynderMultiImg();
                $item_old_value = json_decode($image_value, true);
                $model = $this->bynderMediaTable->create();
                foreach ($item_old_value as $value) {
                    $m_id = trim($value['bynder_md_id']);
                    $modelCollection = $this->bynderMediaTableCollectionFactory->create()
                    ->addFieldToFilter('sku', ['eq' => [$sku]])
                    ->addFieldToFilter('media_id', ['eq' => [$m_id]])->load();
                    if (count($modelCollection) == 0) {
                        $data = [
                            "sku" => $sku,
                            "media_id" => $m_id,
                            "status" => "1"
                        ];
                        $model->setData($data);
                        $model->save();
                    }
                }
                $updated_values = [
                    'bynder_delete_cron' => 1
                ];
                $this->action->updateAttributes(
                    [$product_ids],
                    $updated_values,
                    $storeId
                );
            }
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }
}
