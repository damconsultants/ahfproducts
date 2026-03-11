<?php

namespace DamConsultants\Ahfproducts\Cron;

use Exception;
use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use Magento\Framework\App\Cache\Manager as CacheManager;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\ApiBynderMediaTableCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;

class DeleteValue
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var CacheManager
     */
    protected $cacheManager;
    /**
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $configWriter
     */
    protected $configWriter;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $action
     */
    protected $action;
    /**
     * @var $_byndersycData
     */
    protected $_byndersycData;
    /**
     * @var $collectionFactory
     */
    protected $collectionFactory;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var $bynderMediaTable
     */
    protected $bynderMediaTable;
    /**
     * @var $bynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var $resouce
     */
    protected $resouce;
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
     * @param CacheManager $cacheManager
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
        \Magento\Framework\App\ResourceConnection $resouce,
        CacheManager $cacheManager
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
        $this->cacheManager = $cacheManager;
    }
    /**
     * Execute
     *
     * @return boolean
     */
    public function execute()
    {
        try {
            $enable = $this->datahelper->getDeleteCronEnable();
            if (!$enable) {
                return false;
            }
            $path = 'cronimageconfig/delete_cron_bynder/delete_cron_last_time';
            $isCofigPathExits = $this->datahelper->getStoreConfig($path);
            if (!$isCofigPathExits) {
                $current_time = time();
                $formattedDate = date('Y-m-d H:i:s', $current_time);
                $scope = 'default';
                $add_time = $this->configWriter->save($path, $formattedDate, $scope, $scopeId = 0);
            } else {
                $current_time = $this->datahelper->getDeleteCron($path);
            }
            $bynder_auth["last_cron_time"] = $current_time;
            $get_api_delete_details = $this->datahelper->getCheckBynderSideDeleteData($bynder_auth);
            $response = json_decode($get_api_delete_details, true);
            $data_val_arr = [];
            $data_arr = [];
            if (count($response) > 0) {
                if ($response['status'] == 1) {
                    if (count($response['data']) > 0) {
                        foreach ($response['data'] as $delete_api_data) {
                            if (isset($delete_api_data["id"])) {
                                $isDelete = $this->getDeleteMedaiDataTable($delete_api_data['id']);
                                if ($isDelete) {
                                    $this->getInsertApiMediaTable($delete_api_data['id']);
                                }
                            }
                        }
                    }
                }
            }
            $new_current_time = time();
            $newformattedDate = date('Y-m-d H:i:s', $new_current_time);
            $scope = 'default';
            $update_time = $this->configWriter->save($path, $newformattedDate, $scope, $scopeId = 0);
            $this->cacheManager->flush($this->cacheManager->getAvailableTypes());
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }
    /**
     * Execute
     *
     * @param string $id
     * @param string $sku
     * @return boolean
     */
    public function getInsertDataTable($id, $sku)
    {
        $model = $this->_byndersycData->create();
        $data_image_data = [
            'sku' => $sku,
            'media_id' => $id
        ];
        $model->setData($data_image_data);
        $model->save();
    }
    /**
     * Execute
     *
     * @param string $insert_data
     * @return boolean
     */
    public function getInsertApiMediaTable($insert_data)
    {
        $model = $this->ApiBynderMediaTable->create();
        $modelCollection = $this->ApiBynderMediaTableCollection->create()
        ->addFieldToFilter('media_id', ['eq' => [$insert_data]])->load();
        if (count($modelCollection) == 0) {
            $data_image_data = [
                'media_id' => trim($insert_data)
            ];
            $model->setData($data_image_data);
            $model->save();
        }
    }
    /**
     * Execute
     *
     * @param string $media_id
     * @return boolean
     */
    public function getDeleteMedaiDataTable($media_id)
    {
        $image_detail = [];
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $model = $this->bynderMediaTableCollectionFactory->create()
        ->addFieldToFilter('media_id', ['eq' => [$media_id]])->load();
        if (count($model->getData()) == 0) {
            return true;
        } else {
            foreach ($model as $m_data) {
                $_product = $this->_productRepository->get($m_data->getSku());
                $product_ids = $_product->getId();
                $image_value = $_product->getBynderMultiImg();
                if (!empty($image_value)) {
                    $item_old_value = json_decode($image_value, true);
                    $newArraydata = $this->removeElementByBynderId($item_old_value, $media_id, $m_data->getSku());
                    $jsonArray = array_values($newArraydata);
                    $newArrayJson = json_encode($jsonArray, true);
                    $_product->setData('bynder_multi_img', $newArrayJson);
                    $this->_productRepository->save($_product);
                }
                $this->bynderMediaTable->create()->load($m_data->getId())->delete();
            }
            return false;
        }
    }
    /**
     * Execute
     *
     * @param array $array
     * @param string $id
     * @param string $sku
     * @return array
     */
    public function removeElementByBynderId($array, $id, $sku)
    {
        foreach ($array as $key => $value) {
            if (trim($value['bynder_md_id']) === $id) {
                $this->getInsertDataTable($id, $sku);
                unset($array[$key]);
            }
        }
        return $array;
    }
}
