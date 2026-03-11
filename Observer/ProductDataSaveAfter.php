<?php

namespace DamConsultants\Ahfproducts\Observer;

use Magento\Framework\Event\ObserverInterface;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderTempDocDataCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderTempDataCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderSycDataCollectionFactory;

class ProductDataSaveAfter implements ObserverInterface
{
    /**
     * @var $cookieManager
     */
    protected $cookieManager;
    /**
     * @var $cookieMetadataFactory
     */
    protected $cookieMetadataFactory;
    /**
     * @var $productActionObject
     */
    protected $productActionObject;
    /**
     * @var $_byndersycData
     */
    protected $_byndersycData;
    /**
     * @var $datahelper
     */
    protected $datahelper;
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
     * @var $bynderTempData
     */
    protected $bynderTempData;
    /**
     * @var $bynderTempDataCollectionFactory
     */
    protected $bynderTempDataCollectionFactory;
    /**
     * @var $bynderTempDocData
     */
    protected $bynderTempDocData;
    /**
     * @var $bynderTempDocDataCollectionFactory
     */
    protected $bynderTempDocDataCollectionFactory;
    /**
     * @var $_collection
     */
    protected $_collection;
    /**
     * @var $_resource
     */
    protected $_resource;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $messageManager
     */
    protected $messageManager;
    /**
     * @var $resultRedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * Product save after
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Catalog\Model\Product\Action $productActionObject
     * @param \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $byndersycData
     * @param BynderSycDataCollectionFactory $collection
     * @param \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param DamConsultants\Ahfproducts\Model\BynderTempDataFactory $bynderTempData
     * @param BynderTempDataCollectionFactory $bynderTempDataCollectionFactory
     * @param \DamConsultants\Ahfproducts\Model\BynderTempDocDataFactory $bynderTempDocData
     * @param BynderTempDocDataCollectionFactory $bynderTempDocDataCollectionFactory
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \DamConsultants\Ahfproducts\Helper\Data $DataHelper
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Backend\Model\View\Result\Redirect $resultRedirect
     */

    public function __construct(
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Catalog\Model\Product\Action $productActionObject,
        \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $byndersycData,
        BynderSycDataCollectionFactory $collection,
        \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        \DamConsultants\Ahfproducts\Model\BynderTempDataFactory $bynderTempData,
        BynderTempDataCollectionFactory $bynderTempDataCollectionFactory,
        \DamConsultants\Ahfproducts\Model\BynderTempDocDataFactory $bynderTempDocData,
        BynderTempDocDataCollectionFactory $bynderTempDocDataCollectionFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        \DamConsultants\Ahfproducts\Helper\Data $DataHelper,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Backend\Model\View\Result\Redirect $resultRedirect
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->productActionObject = $productActionObject;
        $this->_byndersycData = $byndersycData;
        $this->datahelper = $DataHelper;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->bynderTempData = $bynderTempData;
        $this->bynderTempDataCollectionFactory = $bynderTempDataCollectionFactory;
        $this->bynderTempDocData = $bynderTempDocData;
        $this->bynderTempDocDataCollectionFactory = $bynderTempDocDataCollectionFactory;
        $this->_collection = $collection;
        $this->_resource = $resource;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirect;
    }
    /**
     * Execute
     *
     * @return $this
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $product = $observer->getProduct();
        $productId = $observer->getProduct()->getId();
        $product_sku_key = $product->getData('sku');
        $bynder_multi_img = $product->getData('bynder_multi_img');
        /**Doing new code and new requirements for theines */
        $bynder_document = $product->getData('bynder_document');
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $connection = $this->_resource->getConnection();
        $image_coockie_id = $this->cookieManager->getCookie('image_coockie_id');
        $doc_coockie_id = $this->cookieManager->getCookie('doc_coockie_id');
        $all_meta_properties = $metaProperty_collection = $this->metaPropertyCollectionFactory->create()->getData();
        $collection_data_value = [];
        $collection_data_slug_val = [];
        $document = "";
        $image = "";
        if ($image_coockie_id != 0) {
            $bynderTempdata = $this->bynderTempDataCollectionFactory->create();
            $bynderTempdata->addFieldToFilter('id', $image_coockie_id)->load();
            if (isset($bynderTempdata)) {
                foreach ($bynderTempdata as $record) {
                    $image = $record['value'];
                }
            }
        } else {
            $image = $bynder_multi_img;
        }
        $new_bynder_array = $image;
        $old_bynder_array = $bynder_multi_img;
        $image_details[] = [
            "old" => $bynder_multi_img,
            "new" => $image
        ];
        if ($doc_coockie_id != 0) {
            $bynderTempdocdata = $this->bynderTempDocDataCollectionFactory->create();
            $bynderTempdocdata->addFieldToFilter('id', $doc_coockie_id)->load();
            if (isset($bynderTempdocdata)) {
                foreach ($bynderTempdocdata as $recorddoc) {
                    $document = $recorddoc['value'];
                }
            }
        } else {
            $document = $bynder_document;
        }
        if (count($metaProperty_collection) >= 1) {
            foreach ($metaProperty_collection as $key => $collection_value) {
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
                    'property_id' => $collection_value['property_id']
                ];
            }
        }
        if (isset($collection_data_slug_val["sku"]["property_id"])) {
            $metaProperty_Collections = $collection_data_slug_val["sku"]["property_id"];
            /******************************Document Section******************************************************************************** */
            if (isset($document)) {
                $this->productActionObject->updateAttributes([$productId], ['bynder_document' => $document], $storeId);
                $this->bynderTempDocData->create()->load($doc_coockie_id)->delete();
                $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                $publicCookieMetadata->setDurationOneYear();
                $publicCookieMetadata->setPath('/');
                $publicCookieMetadata->setHttpOnly(false);

                $this->cookieManager->setPublicCookie(
                    'doc_coockie_id',
                    0,
                    $publicCookieMetadata
                );
            } else {
                $this->productActionObject->updateAttributes([$productId], ['bynder_document' => ""], $storeId);
                $this->bynderTempDocData->create()->load($doc_coockie_id)->delete();
                $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                $publicCookieMetadata->setDurationOneYear();
                $publicCookieMetadata->setPath('/');
                $publicCookieMetadata->setHttpOnly(false);

                $this->cookieManager->setPublicCookie(
                    'doc_coockie_id',
                    0,
                    $publicCookieMetadata
                );
            }
            /***************************Video and Image Section ***************************************************************** */
            $video = "";
            $flag = 0;
            $type = [];
            $m_id = [];
            try {
                if (!empty($image)) {
                    $img_array = json_decode($image, true);
                    foreach ($img_array as $img) {
                        $type[] = $img['item_type'];
                        $m_id[] = $img['bynder_md_id'];
                        $this->getDeleteMedaiDataTable($product_sku_key, $img['bynder_md_id']);
                    }
                    $this->getInsertMedaiDataTable($product_sku_key, $m_id);
                    /*  IMAGE & VIDEO == 1
                    IMAGE == 2
                    VIDEO == 3 */
                    if (in_array("IMAGE", $type) && in_array("VIDEO", $type)) {
                        $flag = 1;
                    } elseif (in_array("IMAGE", $type)) {
                        $flag = 2;
                    } elseif (in_array("VIDEO", $type)) {
                        $flag = 3;
                    }
                    $this->productActionObject->updateAttributes([$productId], ['bynder_isMain' => $flag], $storeId);
                    $this->productActionObject->updateAttributes(
                        [$productId],
                        ['bynder_multi_img' => $image],
                        $storeId
                    );
                    $this->bynderTempData->create()->load($image_coockie_id)->delete();
                    $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                    $publicCookieMetadata->setDurationOneYear();
                    $publicCookieMetadata->setPath('/');
                    $publicCookieMetadata->setHttpOnly(false);
                    $this->cookieManager->setPublicCookie(
                        'image_coockie_id',
                        0,
                        $publicCookieMetadata
                    );
                } else {
                    $this->getDeleteMedaiDataTable($product_sku_key, $m_id);
                    $this->productActionObject->updateAttributes([$productId], ['bynder_isMain' => ""], $storeId);
                    $this->productActionObject->updateAttributes(
                        [$productId],
                        ['bynder_multi_img' => $image],
                        $storeId
                    );
                    $this->productActionObject->updateAttributes([$productId], ['bynder_cron_sync' => ""], $storeId);
                    $this->productActionObject->updateAttributes([$productId], ['bynder_auto_replace' => ""], $storeId);
                    $this->bynderTempData->create()->load($image_coockie_id)->delete();
                    $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                    $publicCookieMetadata->setDurationOneYear();
                    $publicCookieMetadata->setPath('/');
                    $publicCookieMetadata->setHttpOnly(false);
                    $this->cookieManager->setPublicCookie(
                        'image_coockie_id',
                        0,
                        $publicCookieMetadata
                    );
                }
            } catch (\Exception $e) {
                $this->productActionObject->updateAttributes(
                    [$productId],
                    ['bynder_multi_img' => $bynder_multi_img],
                    $storeId
                );
                $this->bynderTempData->create()->load($image_coockie_id)->delete();
                $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
                $publicCookieMetadata->setDurationOneYear();
                $publicCookieMetadata->setPath('/');
                $publicCookieMetadata->setHttpOnly(false);
                $this->cookieManager->setPublicCookie(
                    'image_coockie_id',
                    0,
                    $publicCookieMetadata
                );
            }
        }
    }
    /**
     * Is Json
     *
     * @param string $sku
     * @param array $m_id
     * @return $this
     */
    public function getInsertMedaiDataTable($sku, $m_id)
    {
        $model = $this->bynderMediaTable->create();
        $modelcollection = $this->bynderMediaTableCollectionFactory->create();
        $modelcollection->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        $table_m_id = [];
        if (!empty($modelcollection)) {
            foreach ($modelcollection as $mdata) {
                $table_m_id[] = $mdata['media_id'];
            }
        }
        $media_diff = array_diff($m_id, $table_m_id);
        foreach ($media_diff as $new_data) {
            $data_image_data = [
                'sku' => $sku,
                'media_id' => trim($new_data),
                'status' => "1",
            ];
            $model->setData($data_image_data);
            $model->save();
        }
    }
    /**
     * Is Json
     *
     * @param string $sku
     * @param array $media_id
     * @return $this
     */
    public function getDeleteMedaiDataTable($sku, $media_id)
    {
        $model = $this->bynderMediaTableCollectionFactory->create();
        $model->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        foreach ($model as $mdata) {
            if ($mdata['media_id'] != $media_id) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();

            }
        }
    }
}
