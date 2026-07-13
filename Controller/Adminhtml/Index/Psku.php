<?php

namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;
use Bounteous\SkuAlias\Model\ResourceModel\Alias\CollectionFactory as AliasCollectionFactory;

class Psku extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;
    /**
     * @var logger
     */
    protected $logger;
    /**
     * @var bynderMediaTable
     */
    protected $bynderMediaTable;
    /**
     * @var bynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var _productRepository
     */
    protected $_productRepository;
    /**
     * @var datahelper
     */
    protected $datahelper;
    /**
     * @var productAction
     */
    protected $productAction;
    /**
     * @var _byndersycData
     */
    protected $_byndersycData;
    /**
     * @var metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var configWriter
     */
    protected $configWriter;
    /**
     * @var resouce
     */
    protected $resouce;
    /**
     * @var collectionFactory
     */
    protected $collectionFactory;
    /**
     * @var bynder
     */
    protected $bynder;
    /**
     * @var _resource
     */
    protected $_resource;
    /**
     * @var resultJsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var product
     */
    protected $product;
    private AliasCollectionFactory $aliasCollectionFactory;
    private array $attributeDataCache = [];

    /**
     * Product Sku.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory $byndersycData
     * @param \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \DamConsultants\Ahfproducts\Helper\Data $DataHelper
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param AliasCollectionFactory $aliasCollectionFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory $byndersycData,
        \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \DamConsultants\Ahfproducts\Helper\Data $DataHelper,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        AliasCollectionFactory $aliasCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $jsonFactory;
        $this->productAction = $action;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->datahelper = $DataHelper;
        $this->_resource = $resource;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->_byndersycData = $byndersycData;
        $this->_productRepository = $productRepository;
        $this->product = $product;
        $this->aliasCollectionFactory = $aliasCollectionFactory;
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
            return '';
        }

        $property_id = null;
        $product_sku = $this->getRequest()->getParam('product_sku');
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $result = $this->resultJsonFactory->create();
       
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);

        $collection_value = $meta_properties['collection_data_value'];
        $collection_slug_val = $meta_properties['collection_data_slug_val'];

        if (strlen($product_sku) > 0) {
            $productSku = explode(",", trim($product_sku));
            if (count($productSku) > 0) {
                foreach ($productSku as $sku) {
                    if ($sku != "") {
                        try {
                            $product_id = $this->product->getIdBySku($sku);
                            if (!$product_id) {
                                $insert_data = [
                                    "sku" => $sku,
                                    "message" => "SKU not found in products",
                                    "data_type" => "",
                                    "lable" => "0"
                                ];
                                $this->getInsertDataTable($insert_data);
                                continue;
                            }
                        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                            $insert_data = [
                                "sku" => $sku,
                                "message" => "SKU not match in products",
                                "data_type" => "",
                                "lable" => "0"
                            ];
                            $this->getInsertDataTable($insert_data);
                            continue;
                        }
                        
                        $aliasSku = $this->datahelper->getSkuByAlias($sku);
                        if ($aliasSku === null || empty($aliasSku)) {
                            $aliasSku = $sku;
                            $this->getApiData($aliasSku , null, $property_id, $collection_value, $select_attribute, $collection_slug_val ,$sku);
                        } else {
                            foreach($aliasSku as $a_sku) {
                                $this->getApiData($a_sku['alias_sku'], $a_sku['all_alias_identifier'], $property_id, $collection_value, $select_attribute, $collection_slug_val ,$sku);
                            }
                        }
                    }
                }
            }
            $result_data = $result->setData([
                'status' => 1,
                'message' => 'Data Sync Successfully.Please check AHF Synchronization Log.!'
            ]);
            return $result_data;
        } else {
            $result_data = $result->setData(['status' => 0, 'message' => 'Please enter atleast one SKU.']);
            return $result_data;
        }
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
                    'bynder_property_slug' => $collection_value['system_slug'],
                ];
            }
        }
        $response_array = [
            "collection_data_value" => $collection_data_value,
            "collection_data_slug_val" => $collection_data_slug_val
        ];
        return $response_array;
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return bool
     */
    public function getIsJSON($string)
    {
        if ($string === null || $string === '') {
            return false;
        }
        return ((json_decode($string)) === null) ? false : true;
    }
    
    /**
     * Insert Data Table
     *
     * @param array $insert_data
     * @return $this
     */
    public function getInsertDataTable($insert_data)
    {
        $model = $this->_byndersycData->create();
        $data_image_data = [
            'sku' => $insert_data['sku'],
            'bynder_sync_data' => $insert_data['message'],
            'bynder_data_type' => $insert_data['data_type'],
            'lable' => $insert_data['lable']
        ];
        $model->setData($data_image_data);
        $model->save();
    }
    
    /**
     * Insert Media Data Table
     *
     * @param string $sku
     * @param array $m_id
     * @param string $product_ids
     * @param string $storeId
     * @return $this
     */
    public function getInsertMedaiDataTable($sku, $m_id, $product_ids, $storeId)
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
            $new_m_id = trim($new_data);
            $data_image_data = [
                'sku' => $sku,
                'media_id' => $new_m_id,
                'status' => "1",
            ];
            $model->setData($data_image_data);
            $model->save();
        }
        $updated_values = [
            'bynder_delete_cron' => 1
        ];
        $this->productAction->updateAttributes(
            [$product_ids],
            $updated_values,
            $storeId
        );
    }
    
    /**
     * Delete Media Data Table
     *
     * @param string $sku
     * @param string $media_id
     * @return $this
     */
    public function getDeleteMedaiDataTable($sku, $media_id)
    {
        $model = $this->bynderMediaTableCollectionFactory->create()->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        foreach ($model as $mdata) {
            if ($mdata['media_id'] != $media_id) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();
            }
        }
    }
    
    /**
     * Get Data Item
     *
     * @param string $select_attribute
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     * @param string $alias_sku
     * @param string $all_alias_identifier
     */
    public function getDataItem($select_attribute, $convert_array, $collection_data_slug_val, $current_sku, $alias_sku, $all_alias_identifier)
    {
        $data_arr = [];
        $data_val_arr = [];
        $doc_data_arr = [];
        $doc_data = [];
        $result = $this->resultJsonFactory->create();
        if ($convert_array['status'] == 1) {
            $media_items = [];
            if (isset($convert_array['data']) && is_array($convert_array['data'])) {
                $first_item = reset($convert_array['data']);
                $is_grouped_by_sku = is_array($first_item)
                    && !isset($first_item['type'])
                    && !isset($first_item['id'])
                    && !isset($first_item['thumbnails'])
                    && !isset($first_item['derivatives']);

                if ($is_grouped_by_sku) {
                    foreach ($convert_array['data'] as $group_key => $group_items) {
                        if (!is_array($group_items)) {
                            continue;
                        }
                        foreach ($group_items as $data_value) {
                            if (is_array($data_value)) {
                                $media_items[] = [
                                    'group_key' => $group_key,
                                    'data_value' => $data_value
                                ];
                            }
                        }
                    }
                } else {
                    foreach ($convert_array['data'] as $data_value) {
                        if (is_array($data_value)) {
                            $media_items[] = [
                                'group_key' => null,
                                'data_value' => $data_value
                            ];
                        }
                    }
                }
            }

            foreach ($media_items as $media_item) {
                $data_value = $media_item['data_value'];
                $is_order = array();

                $item_type = strtolower($data_value['item_type'] ?? $data_value['type'] ?? '');
                $has_direct_item_payload = isset($data_value['item_url']) && !empty($data_value['item_url']) && !isset($data_value['thumbnails']) && !isset($data_value['derivatives']);

                if ($has_direct_item_payload) {
                    $data_sku[0] = $current_sku;
                    $item_url = $data_value['item_url'] ?? '';
                    $item_is_order = isset($data_value['is_order']) ? $data_value['is_order'] : '';
                    $item_alias_identifier = $data_value['all_alias_identifier'] ?? $all_alias_identifier;

                    if (($select_attribute === 'image' && $item_type === 'image') || ($select_attribute === 'all_attribute' && in_array($item_type, ['image', 'video', 'document'], true))) {
                        if ($item_type === 'image') {
                            $image_roles = isset($data_value['image_role']) && is_array($data_value['image_role'])
                                ? array_values(array_filter($data_value['image_role'], function ($role) {
                                    return trim($role) !== '';
                                }))
                                : [];
                            $alt_text = isset($data_value['alt_text']) ? $data_value['alt_text'] : '';
                            array_push($data_arr, $data_sku[0]);
                            $data_p = [
                                "sku" => $data_sku[0],
                                "url" => [$item_url . "\n"],
                                'magento_image_role' => $image_roles,
                                'image_alt_text' => [(!empty($alt_text) ? $alt_text : '###') . "\n"],
                                'bynder_media_id_new' => [$data_value['bynder_md_id'] ?? ''],
                                'is_order' => [$item_is_order . "\n"],
                                'alias_sku' => $alias_sku,
                                'all_alias_identifier' => $item_alias_identifier
                            ];
                            array_push($data_val_arr, $data_p);
                        } elseif ($item_type === 'video') {
                            $video_link = $item_url;
                            array_push($data_arr, $data_sku[0]);
                            $data_p = [
                                "sku" => $data_sku[0],
                                "url" => [$video_link . "\n"],
                                'magento_image_role' => [],
                                'image_alt_text' => [isset($data_value['alt_text']) && !empty($data_value['alt_text']) ? $data_value['alt_text'] : '###' . "\n"],
                                'bynder_media_id_new' => [$data_value['bynder_md_id'] ?? ''],
                                "type" => "video",
                                'is_order' => [$item_is_order . "\n"],
                                'alias_sku' => $alias_sku,
                                'all_alias_identifier' => $item_alias_identifier
                            ];
                            array_push($data_val_arr, $data_p);
                        } elseif ($item_type === 'document') {
                            $doc_name = isset($data_value['doc_name']) ? $data_value['doc_name'] : (isset($data_value['alt_text']) ? $data_value['alt_text'] : 'document');
                            $doc_link = $item_url . '@@' . $doc_name . "\n";
                            array_push($data_arr, $data_sku[0]);
                            $data_p = [
                                "sku" => $data_sku[0],
                                "url" => [$doc_link],
                                'magento_image_role' => [],
                                'image_alt_text' => [isset($data_value['alt_text']) && !empty($data_value['alt_text']) ? $data_value['alt_text'] : '###' . "\n"],
                                'bynder_media_id_new' => [$data_value['bynder_md_id'] ?? ''],
                                'is_order' => [$item_is_order . "\n"],
                                'alias_sku' => $alias_sku,
                                'all_alias_identifier' => $item_alias_identifier
                            ];
                            array_push($data_val_arr, $data_p);
                        }
                    }
                    continue;
                }
                
                if ($select_attribute == $data_value['type']) {
                    $bynder_media_id = $data_value['id'];
                    $image_data = $data_value['thumbnails'];
                    $bynder_image_role = $image_data['magento_role_options'];
                    $bynder_alt_text = $image_data['img_alt_text'];
                    $sku_slug_name = "property_" . $collection_data_slug_val['sku']['bynder_property_slug'];
                    $data_sku[0] = $current_sku;
                    
                    $images_urls_list = [];
                    $new_magento_role_list = [];
                    $new_bynder_alt_text = [];
                    $new_bynder_mediaid_text = [];
                    $new_image_role = [];
                    
                    if (count($bynder_image_role) > 0) {
                        foreach ($bynder_image_role as $m_bynder_role) {
                            if (!empty($m_bynder_role)) {
                                if($m_bynder_role == "Thumb") {
                                    $m_bynder_role = 'Thumbnail';
                                }
                                $new_magento_role_list[] = $m_bynder_role;
                                $alt_text_vl = $data_value["thumbnails"]["img_alt_text"];
                                if (is_array($data_value["thumbnails"]["img_alt_text"])) {
                                    $alt_text_vl = implode(" ", $data_value["thumbnails"]["img_alt_text"]);
                                }
                                if (empty($alt_text_vl)) {
                                    $new_bynder_alt_text[] = "###\n";
                                } else {
                                    $new_bynder_alt_text[] = $alt_text_vl."\n";
                                }
                                $new_bynder_mediaid_text[] = $bynder_media_id;
                                $magento_order_slug = $collection_data_slug_val['image_order']['bynder_property_slug'];
                                if(isset($data_value[$magento_order_slug])) {
                                    if(count($data_value[$magento_order_slug]) > 0) {
                                        foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                            $is_order[] = $property_Magento_Media_Order . "\n";
                                        }
                                    }
                                }
                            } else {
                                $new_magento_role_list[] = "###"."\n";
                                $alt_text_vl = $data_value["thumbnails"]["img_alt_text"];
                                if (!empty($alt_text_vl)) {
                                    $new_bynder_alt_text[] = $alt_text_vl."\n";
                                } else {
                                    $new_bynder_alt_text[] = "###\n";
                                }
                                $new_bynder_mediaid_text[] = $bynder_media_id;
                                $magento_order_slug = $collection_data_slug_val['image_order']['bynder_property_slug'];
                                if(isset($data_value[$magento_order_slug])) {
                                    if(count($data_value[$magento_order_slug]) > 0) {
                                        foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                            $is_order[] = $property_Magento_Media_Order . "\n";
                                        }
                                    }
                                }
                            }
                        }
                        $is_order = array_unique($is_order);
                    } else {
                        if($data_value["is_base"] == 0){
                            $new_magento_role_list[] = "###"."\n";    
                        } else {
                            $new_magento_role_list = ['Base', 'Small', 'Thumbnail', 'Swatch']; 
                        }
                        $alt_text_vl = $data_value["thumbnails"]["img_alt_text"];
                        if (!empty($alt_text_vl)) {
                            $new_bynder_alt_text[] = $alt_text_vl."\n";
                        } else {
                            $new_bynder_alt_text[] = "###\n";
                        }
                        $new_bynder_mediaid_text[] = $bynder_media_id;
                        $magento_order_slug = $collection_data_slug_val['image_order']['bynder_property_slug'];
                        if(isset($data_value[$magento_order_slug])) {
                            if(count($data_value[$magento_order_slug]) > 0) {
                                foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                    $is_order[] = $property_Magento_Media_Order . "\n";
                                }
                            }
                        }
                    }
                    $new_bynder_alt_text = array_unique($new_bynder_alt_text);
                    $new_bynder_mediaid_text = array_unique($new_bynder_mediaid_text);
                    
                    if ($data_value['type'] == "image") {
                        $image_link = "";
                        if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
                            foreach ($data_value['derivatives'] as $derivative) {
                                if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                                    $image_link = $derivative['public_url'];
                                    break;
                                }
                            }
                        }
                        array_push($data_arr, $data_sku[0]);
                        $data_p = [
                            "sku" => $data_sku[0],
                            "url" => [$image_link."\n"],
                            'magento_image_role' => $new_magento_role_list,
                            'image_alt_text' => $new_bynder_alt_text,
                            'bynder_media_id_new' => $new_bynder_mediaid_text,
                            'is_order' => $is_order,
                            'alias_sku' => $alias_sku,
                            'all_alias_identifier' => $all_alias_identifier
                        ];
                        array_push($data_val_arr, $data_p);
                    } else {
                        if ($data_value['type'] == 'video') {
                            $new_image_role = [];
                            $video_link = "";
                            if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
                                foreach ($data_value['derivatives'] as $derivative) {
                                    if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                                        $video_link = $derivative['public_url'] . '@@' . $derivative['main_link'];
                                        break;
                                    } else {
                                        $video_link = $derivative['s3_link'] . '@@' . $derivative['main_link'];
                                    }
                                }
                            }
                            array_push($data_arr, $data_sku[0]);
                            $data_p = [
                                "sku" => $data_sku[0],
                                "url" => [$video_link."\n"],
                                'magento_image_role' => $new_image_role,
                                'image_alt_text' => $new_bynder_alt_text,
                                'bynder_media_id_new' => $new_bynder_mediaid_text,
                                "type" => "video",
                                'is_order' => $is_order,
                                'alias_sku' => $alias_sku,
                                'all_alias_identifier' => $all_alias_identifier
                            ];
                            array_push($data_val_arr, $data_p);
                        } else {
                            $new_image_role = [];
                            $doc_name = $data_value["name"];
                            $doc_link = "";
                            if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
                                foreach ($data_value['derivatives'] as $derivative) {
                                    if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                                        $doc_link = $derivative['public_url'] . '@@' . $doc_name . "\n";
                                        break;
                                    }
                                }
                            }
                            if (!empty($doc_link)) {
                                array_push($data_arr, $data_sku[0]);
                                $data_p = [
                                    "sku" => $data_sku[0],
                                    "url" => [$doc_link],
                                    'magento_image_role' => $new_image_role,
                                    'image_alt_text' => $new_bynder_alt_text,
                                    'bynder_media_id_new' => $new_bynder_mediaid_text,
                                    'is_order' => $is_order,
                                    'alias_sku' => $alias_sku,
                                    'all_alias_identifier' => $all_alias_identifier
                                ];
                                array_push($data_val_arr, $data_p);
                            }
                        }
                    }
                } elseif($select_attribute == 'all_attribute') {
                    $bynder_media_id = $data_value['id'];
                    $image_data = $data_value['thumbnails'];
                    $bynder_image_role = $image_data['magento_role_options'];
                    $bynder_alt_text = $image_data['img_alt_text'];
                    $sku_slug_name = "property_" . $collection_data_slug_val['sku']['bynder_property_slug'];
                    $data_sku[0] = $current_sku;
                    
                    $images_urls_list = [];
                    $new_magento_role_list = [];
                    $new_bynder_alt_text = [];
                    $new_bynder_mediaid_text = [];
                    $new_image_role = [];
                    
                    if (count($bynder_image_role) > 0) {
                        foreach ($bynder_image_role as $m_bynder_role) {
                            if (!empty($m_bynder_role)) {
                                if($m_bynder_role == "Thumb") {
                                    $m_bynder_role = 'Thumbnail';
                                }
                                $new_magento_role_list[] = $m_bynder_role;
                                $alt_text_vl = $data_value["thumbnails"]["img_alt_text"];
                                if (is_array($data_value["thumbnails"]["img_alt_text"])) {
                                    $alt_text_vl = implode(" ", $data_value["thumbnails"]["img_alt_text"]);
                                }
                                if (empty($alt_text_vl)) {
                                    $new_bynder_alt_text[] = "###\n";
                                } else {
                                    $new_bynder_alt_text[] = $alt_text_vl."\n";
                                }
                                $new_bynder_mediaid_text[] = $bynder_media_id;
                                $magento_order_slug = $collection_data_slug_val['image_order']['bynder_property_slug'];
                                if(isset($data_value[$magento_order_slug])) {
                                    if(count($data_value[$magento_order_slug]) > 0) {
                                        foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                            $is_order[] = $property_Magento_Media_Order . "\n";
                                        }
                                    }
                                }
                            } else {
                                $new_magento_role_list[] = "###"."\n";
                                $alt_text_vl = $data_value["thumbnails"]["img_alt_text"];
                                if (!empty($alt_text_vl)) {
                                    $new_bynder_alt_text[] = $alt_text_vl."\n";
                                } else {
                                    $new_bynder_alt_text[] = "###\n";
                                }
                                $new_bynder_mediaid_text[] = $bynder_media_id;
                                $magento_order_slug = $collection_data_slug_val['image_order']['bynder_property_slug'];
                                if(isset($data_value[$magento_order_slug])) {
                                    if(count($data_value[$magento_order_slug]) > 0) {
                                        foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                            $is_order[] = $property_Magento_Media_Order . "\n";
                                        }
                                    }
                                }
                            }
                        }
                        $is_order = array_unique($is_order);
                    } else {
                        if($data_value["is_base"] == 0){
                            $new_magento_role_list[] = "###"."\n";    
                        } else {
                            $new_magento_role_list = ['Base', 'Small', 'Thumbnail', 'Swatch']; 
                        }
                        $alt_text_vl = $data_value["thumbnails"]["img_alt_text"];
                        if (!empty($alt_text_vl)) {
                            $new_bynder_alt_text[] = $alt_text_vl."\n";
                        } else {
                            $new_bynder_alt_text[] = "###\n";
                        }
                        $new_bynder_mediaid_text[] = $bynder_media_id;
                        $magento_order_slug = $collection_data_slug_val['image_order']['bynder_property_slug'];
                        if(isset($data_value[$magento_order_slug])) {
                            if(count($data_value[$magento_order_slug]) > 0) {
                                foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                    $is_order[] = $property_Magento_Media_Order . "\n";
                                }
                            }
                        }
                    }
                    $new_bynder_mediaid_text = array_unique($new_bynder_mediaid_text);
                    $new_bynder_alt_text = array_unique($new_bynder_alt_text);
                    
                    if ($data_value['type'] == "image") {
                        $image_link = "";
                        if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
                            foreach ($data_value['derivatives'] as $derivative) {
                                if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                                    $image_link = $derivative['public_url'];
                                    break;
                                }
                            }
                        }
                        array_push($data_arr, $data_sku[0]);
                        $data_p = [
                            "sku" => $data_sku[0],
                            "url" => [$image_link."\n"],
                            'magento_image_role' => $new_magento_role_list,
                            'image_alt_text' => $new_bynder_alt_text,
                            'bynder_media_id_new' => $new_bynder_mediaid_text,
                            'is_order' => $is_order,
                            'alias_sku' => $alias_sku,
                            'all_alias_identifier' => $all_alias_identifier
                        ];
                        array_push($data_val_arr, $data_p);
                    } else {
                        if ($data_value['type'] == 'video') {
                            $video_link = "";
                            if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
                                foreach ($data_value['derivatives'] as $derivative) {
                                    if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                                        $video_link = $derivative['public_url'] . '@@' . $derivative['main_link'];
                                        break;
                                    } else {
                                        $video_link = $derivative['s3_link'] . '@@' . $derivative['main_link'];
                                    }
                                }
                            }
                            array_push($data_arr, $data_sku[0]);
                            $data_p = [
                                "sku" => $data_sku[0],
                                "url" => [$video_link."\n"],
                                'magento_image_role' => $new_image_role,
                                'image_alt_text' => $new_bynder_alt_text,
                                'bynder_media_id_new' => $new_bynder_mediaid_text,
                                'is_order' => $is_order,
                                "type" => "video",
                                'alias_sku' => $alias_sku,
                                'all_alias_identifier' => $all_alias_identifier
                            ];
                            array_push($data_val_arr, $data_p);
                        } else {
                            $doc_name = $data_value["name"];
                            $doc_link = "";
                            if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
                                foreach ($data_value['derivatives'] as $derivative) {
                                    if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                                        $doc_link = $derivative['public_url'] . '@@' . $doc_name . "\n";
                                        break;
                                    }
                                }
                            }
                            if (!empty($doc_link)) {
                                array_push($doc_data_arr, $data_sku[0]);
                                $data_p = [
                                    "sku" => $data_sku[0],
                                    "url" => [$doc_link],
                                    'magento_image_role' => $new_image_role,
                                    'image_alt_text' => $new_bynder_alt_text,
                                    'bynder_media_id_new' => $new_bynder_mediaid_text,
                                    'is_order' => $is_order,
                                    'alias_sku' => $alias_sku,
                                    'all_alias_identifier' => $all_alias_identifier
                                ];
                                array_push($doc_data, $data_p);
                            }
                        }
                    }
                }
                
            }
            
        }
        
        if (count($data_arr) > 0) {
            $this->getProcessItem($data_arr, $data_val_arr);
        }
        if (count($doc_data_arr) > 0) {
            $this->getProcessItemDoc($doc_data_arr, $doc_data);
        } 
        if (count($doc_data_arr) == 0 && count($data_arr) == 0) {
            $result_data = $result->setData(['status' => 0, 'message' => 'No Data Found...']);
            return $result_data;
        }
    }
    
    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @return $this
     */
    public function getProcessItem($data_arr, $data_val_arr)
    {
        $result = $this->resultJsonFactory->create();
        $image_value_details_role = [];
        $temp_arr = [];
        $byn_is_order = [];
        $alias_sku = [];
        $all_alias_identifier = [];
        
        foreach ($data_arr as $key => $skus) {
            $alias_value = isset($data_val_arr[$key]['alias_sku']) ? $data_val_arr[$key]['alias_sku'] : '';
            $group_key = $skus;
            if (!empty($alias_value)) {
                $group_key = $skus . '||' . $alias_value;
            }

            $temp_arr[$group_key][] = implode("", $data_val_arr[$key]["url"]);
            $image_value_details_role[$group_key][] = $data_val_arr[$key]["magento_image_role"];
            $image_alt_text[$group_key][] = implode("", $data_val_arr[$key]["image_alt_text"]);
            $byn_md_id_new[$group_key][] = implode("", $data_val_arr[$key]["bynder_media_id_new"]);
            $byn_is_order[$group_key][] = implode("", $data_val_arr[$key]["is_order"]);
            $alias_sku[$group_key][] = $alias_value;
            
            // Handle all_alias_identifier - store as string
            $all_alias_identifier[$group_key][]  = $data_val_arr[$key]["all_alias_identifier"];
        }
        
        foreach ($temp_arr as $group_key => $image_value) {
            $img_json = implode("", $image_value);
            $mg_role = $image_value_details_role[$group_key];
            $image_alt_text_value = implode("", $image_alt_text[$group_key]);
            $byd_media_is_order = implode("", $byn_is_order[$group_key]);
            $byd_alias_sku = $alias_sku[$group_key];
            $byn_all_alias_identifier = $all_alias_identifier[$group_key];

            $product_sku_key = $group_key;
            if (strpos($group_key, '||') !== false) {
                [$product_sku_key] = explode('||', $group_key, 2);
            }
            
            $group_media_ids = $byn_md_id_new[$group_key] ?? [];
            $this->getUpdateImage(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $group_media_ids,
                $byd_media_is_order,
                $byd_alias_sku,
                $byn_all_alias_identifier
            );
        }
    }
    
    /**
     * Get Process Item Document
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @return $this
     */
    public function getProcessItemDoc($data_arr, $data_val_arr)
    {
        $result = $this->resultJsonFactory->create();
        $image_value_details_role = [];
        $temp_arr = [];
        $byn_is_order = [];
        
        foreach ($data_arr as $key => $skus) {
            $alias_value = isset($data_val_arr[$key]['alias_sku']) ? $data_val_arr[$key]['alias_sku'] : '';
            $group_key = $skus;
            if (!empty($alias_value)) {
                $group_key = $skus . '||' . $alias_value;
            }

            $temp_arr[$group_key][] = implode("", $data_val_arr[$key]["url"]);
            $image_value_details_role[$group_key][] = $data_val_arr[$key]["magento_image_role"];
            $image_alt_text[$group_key][] = implode("", $data_val_arr[$key]["image_alt_text"]);
            $byn_md_id_new[$group_key][] = implode("", $data_val_arr[$key]["bynder_media_id_new"]);
            $byn_is_order[$group_key][] = implode("", $data_val_arr[$key]["is_order"]);
        }
        
        foreach ($temp_arr as $group_key => $image_value) {
            $img_json = implode("", $image_value);
            $mg_role = $image_value_details_role[$group_key];
            $image_alt_text_value = implode("", $image_alt_text[$group_key]);
            $byd_media_is_order = implode("", $byn_is_order[$group_key]);
            $product_sku_key = $group_key;
            if (strpos($group_key, '||') !== false) {
                [$product_sku_key] = explode('||', $group_key, 2);
            }
            $group_media_ids = $byn_md_id_new[$group_key] ?? [];
            $this->getUpdateDoc(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $group_media_ids,
                $byd_media_is_order
            );
        }
    }
    
    /**
     * Update Document
     *
     * @param string $img_json
     * @param string $product_sku_key
     * @param string $mg_img_role_option
     * @param string $img_alt_text
     * @param array $bynder_media_ids
     * @param string $byd_media_is_order
     * @return $this
     */
    public function getUpdateDoc($img_json, $product_sku_key, $mg_img_role_option, $img_alt_text, $bynder_media_ids, $byd_media_is_order, $byd_alias_sku, $byn_all_alias_identifier)
    {
        $result = $this->resultJsonFactory->create();
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $image_detail = [];
        $video_detail = [];
        $diff_image_detail = [];
        
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();
            
            // Get existing bynder_document data from the database so repeated updates preserve prior alias groups.
            $doc_values = $this->getExistingAttributeData($_product, 'bynder_document', $storeId);
            
            $bynder_media_id = [];
            if (isset($bynder_media_ids[$product_sku_key]) && is_array($bynder_media_ids[$product_sku_key])) {
                $bynder_media_id = $bynder_media_ids[$product_sku_key];
            } elseif (is_array($bynder_media_ids)) {
                $bynder_media_id = $bynder_media_ids;
            }
            $isOrder = explode("\n", $byd_media_is_order);
            
            // Initialize log data array for documents
            $log_documents = [];
            
            // Get alias key
            $alias_key = isset($byd_alias_sku[0]) ? $byd_alias_sku[0] : $product_sku_key;
            
            if (empty($doc_values)) {
                $new_doc_array = explode("\n", $img_json);
                $doc_detail = [];
                foreach ($new_doc_array as $vv => $doc_value) {
                    $item_url = explode("@@", $doc_value);
                    $doc_name = explode("@@", $doc_value);
                    $media_doc_explode = explode("/", $item_url[0]);
                    $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                    $alias_identifier = isset($byn_all_alias_identifier[$vv]) ? $byn_all_alias_identifier[$vv] : (isset($byn_all_alias_identifier[0]) ? $byn_all_alias_identifier[0] : "");
                    
                    if(isset($doc_name[1]) && isset($bynder_media_id[$vv])){
                        $doc_detail[] = [
                            "item_url" => $item_url[0],
                            "item_type" => 'DOCUMENT',
                            "doc_name" => $doc_name[1],
                            "bynder_md_id" => $bynder_media_id[$vv],
                            "is_order" => $is_order,
                            "all_alias_identifier" => $alias_identifier
                        ];
                        
                        // Collect log data for documents
                        $log_documents[] = $item_url[0];
                    }
                }
                
                $docData = [];
                if (!empty($doc_detail)) {
                    $docData[$product_sku_key] = $doc_detail;
                }
                $new_value_array = json_encode($docData, true);
                
                $this->productAction->updateAttributes(
                    [$product_ids],
                    ['bynder_document' => $new_value_array],
                    $storeId
                );
            } else {
                $item_old_value = $doc_values;
                if (is_array($item_old_value)) {
                    $skuExistingItems = isset($item_old_value[$product_sku_key]) ? $item_old_value[$product_sku_key] : [];
                    $all_item_url = [];
                    $b_id = [];
                    
                    if (count($skuExistingItems) > 0) {
                        foreach ($skuExistingItems as $doc) {
                            if ($doc['item_type'] == 'DOCUMENT') {
                                $all_item_url[] = $doc['item_url'];
                                $b_id[] = $doc['bynder_md_id'];
                            }
                        }
                    }
                }
                
                $new_doc_array = explode("\n", $img_json);
                $doc_detail = [];
                foreach ($new_doc_array as $vv => $doc_value) {
                    if(!empty($doc_value)){
                        $item_url = explode("@@", $doc_value);
                        $doc_name = explode("@@", $doc_value);
                        $media_doc_explode = explode("/", $item_url[0]);
                        $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                        $alias_identifier = isset($byn_all_alias_identifier[$vv]) ? $byn_all_alias_identifier[$vv] : (isset($byn_all_alias_identifier[0]) ? $byn_all_alias_identifier[0] : "");
                        
                        if(isset($doc_name[1]) && isset($bynder_media_id[$vv])){
                            $doc_detail[] = [
                                "item_url" => $item_url[0],
                                "item_type" => 'DOCUMENT',
                                "doc_name" => $doc_name[1],
                                "bynder_md_id" => $bynder_media_id[$vv],
                                "is_order" => $is_order,
                                "all_alias_identifier" => $alias_identifier
                            ];
                            
                            // Collect log data for documents
                            $log_documents[] = $item_url[0];
                        }
                    }
                }
                
                $existingGroupItems = isset($doc_values[$product_sku_key]) && is_array($doc_values[$product_sku_key])
                    ? $doc_values[$product_sku_key]
                    : [];

                $mergedDocItems = $existingGroupItems;
                foreach ($doc_detail as $new_doc_item) {
                    $itemUrl = $new_doc_item['item_url'] ?? '';
                    $isDuplicate = false;
                    foreach ($mergedDocItems as $existingDocItem) {
                        if (($existingDocItem['item_url'] ?? '') === $itemUrl) {
                            $isDuplicate = true;
                            break;
                        }
                    }
                    if (!$isDuplicate && $itemUrl !== '') {
                        $mergedDocItems[] = $new_doc_item;
                    }
                }

                if (!empty($mergedDocItems)) {
                    $doc_values[$product_sku_key] = $mergedDocItems;
                } else {
                    unset($doc_values[$product_sku_key]);
                }
                
                $new_value_array = json_encode($doc_values, true);
                $this->productAction->updateAttributes(
                    [$product_ids],
                    ['bynder_document' => $new_value_array],
                    $storeId
                );
            }
            
            // Insert log for documents (data_type = 3)
            if (!empty($log_documents)) {
                $log_value_array = json_encode($log_documents, true);
                $insert_data = [
                    "sku" => $product_sku_key . " alias sku " . $alias_key,
                    "message" => $log_value_array,
                    "data_type" => "3", // 3 = Document
                    "lable" => 1
                ];
                $this->getInsertDataTable($insert_data);
            }
            
        } catch (\Exception $e) {
            return $result->setData(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Update Image
     *
     * @param string $img_json
     * @param string $product_sku_key
     * @param array $mg_img_role_option
     * @param string $img_alt_text
     * @param array $bynder_media_ids
     * @param string $byd_media_is_order
     * @param string $byd_alias_sku
     * @param string $byn_all_alias_identifier
     * @return $this
     */
    public function getUpdateImage($img_json, $product_sku_key, $mg_img_role_option, $img_alt_text, $bynder_media_ids, $byd_media_is_order, $byd_alias_sku, $byn_all_alias_identifier)
    {
        $result = $this->resultJsonFactory->create();
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $image_detail = [];
        $video_detail = [];
        $diff_image_detail = [];
        
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();
            
            // Get existing bynder_multi_img data from the database so repeated alias updates preserve prior entries.
            $image_value = $this->getExistingAttributeData($_product, 'bynder_multi_img', $storeId);
            $doc_value = $_product->getBynderDocument();
            $bynder_media_id = [];
            if (isset($bynder_media_ids[$product_sku_key]) && is_array($bynder_media_ids[$product_sku_key])) {
                $bynder_media_id = $bynder_media_ids[$product_sku_key];
            } elseif (is_array($bynder_media_ids)) {
                $bynder_media_id = $bynder_media_ids;
            }
            $alias_key = isset($byd_alias_sku[0]) ? $byd_alias_sku[0] : $product_sku_key;
            $isOrder = explode("\n", $byd_media_is_order);
            if ($select_attribute == "image") {
                //$alias_key = isset($byd_alias_sku[0]) ? $byd_alias_sku[0] : $product_sku_key;
            
                if (!empty($image_value)) {
                    $new_image_array = explode("\n", $img_json);
                    $new_alttext_array = explode("\n", $img_alt_text);
                    $new_magento_role_option_array = $mg_img_role_option;
            
                    $skuExistingItems = isset($image_value[$alias_key]) ? $image_value[$alias_key] : [];
                    $all_item_url = [];
            
                    if (count($skuExistingItems) > 0) {
                        foreach ($skuExistingItems as $img) {
                            $all_item_url[] = $img['item_url'];
                        }
                    }
            
                    $image_detail = [];
                    foreach ($new_image_array as $vv => $new_image_value) {
                        if (trim($new_image_value) != "" && $new_image_value != "no image") {
                            $item_url = explode("?", $new_image_value);
                            $media_image_explode = explode("/", $item_url[0]);
                            $img_altText_val = "";
                            if (isset($new_alttext_array[$vv])) {
                                if ($new_alttext_array[$vv] != "###" && strlen(trim($new_alttext_array[$vv])) > 0) {
                                    $img_altText_val = $new_alttext_array[$vv];
                                }
                            }
                            $curt_img_role = [];
                            if ($new_magento_role_option_array[$vv] != "###") {
                                $curt_img_role = $new_magento_role_option_array[$vv];
                            }
                            $find_video = strpos($new_image_value, "@@");
                            $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                            $alias_identifier = isset($byn_all_alias_identifier[$vv]) ? $byn_all_alias_identifier[$vv] : (isset($byn_all_alias_identifier[0]) ? $byn_all_alias_identifier[0] : "");
            
                            if (!$find_video) {
                                $image_detail[] = [
                                    "item_url" => $new_image_value,
                                    "alt_text" => $img_altText_val,
                                    "image_role" => $curt_img_role,
                                    "item_type" => 'IMAGE',
                                    "thum_url" => $item_url[0],
                                    "bynder_md_id" => $bynder_media_id[$vv],
                                    "is_import" => 0,
                                    "is_order" => $is_order,
                                    "all_alias_identifier" => $alias_identifier
                                ];
            
                                $total_new_values = count($image_detail);
                                if ($total_new_values > 1) {
                                    foreach ($image_detail as $nn => $n_img) {
                                        if ($n_img['item_type'] == "IMAGE" && $nn != ($total_new_values - 1)) {
                                            if ($new_magento_role_option_array[$vv] != "###") {
                                                $new_mg_role_array = (array)$new_magento_role_option_array[$vv];
                                                if (count($n_img["image_role"]) > 0 && count($new_mg_role_array) > 0) {
                                                    $result_val = array_diff($n_img["image_role"], $new_mg_role_array);
                                                    $image_detail[$nn]["image_role"] = $result_val;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
            
                    // Process roles
                    $replacementRoles = ["Base", "Small", "Swatch", "Thumbnail"];
                    $flags = true;
                    foreach ($image_detail as &$item) {
                        if (in_array('Base', $item['image_role'])) {
                            $flags = false;
                        }
                    }
                    foreach ($image_detail as &$item) {
                        if ($flags && isset($item['image_role']) && is_array($item['image_role'])) {
                            $containsPlaceholder = in_array("###\n", $item['image_role']);
                            $hasAllReplacementRoles = empty(array_diff($replacementRoles, $item['image_role']));
                            if ($hasAllReplacementRoles) { break; }
                            if ($containsPlaceholder && !$hasAllReplacementRoles) {
                                $item['image_role'] = $replacementRoles;
                            }
                        }
                    }
                    foreach ($image_detail as &$items) {
                        if (isset($items['image_role']) && is_array($items['image_role'])) {
                            $items['image_role'] = array_values(array_filter(
                                $items['image_role'],
                                fn($role) => trim($role) !== '###'
                            ));
                        }
                    }
                    unset($items);

                    $existing_items = [];
                    if (isset($image_value[$alias_key]) && is_array($image_value[$alias_key])) {
                        $existing_items = $image_value[$alias_key];
                    } elseif (isset($image_value[$product_sku_key]) && is_array($image_value[$product_sku_key])) {
                        $existing_items = $image_value[$product_sku_key];
                    }

                    $merged_items = $existing_items;
                    $log_data = [];
                    foreach ($image_detail as $new_item) {
                        $item_url = $new_item['item_url'] ?? '';
                        $log_data[] = $new_item['item_url'];
                        $is_duplicate = false;
                        foreach ($merged_items as $existing_item) {
                            if (($existing_item['item_url'] ?? '') === $item_url) {
                                $is_duplicate = true;
                                break;
                            }
                        }
                        if (!$is_duplicate && $item_url !== '') {
                            $merged_items[] = $new_item;
                        }
                    }

                    if (!empty($merged_items)) {
                        $image_value[$alias_key] = $merged_items;
                    } else {
                        unset($image_value[$alias_key]);
                    }
                    
                    $new_value_array = json_encode($image_value, true);
                    $log_value_array = json_encode($log_data, true);
                    
                    $this->setAttributeDataCache($_product, 'bynder_multi_img', $image_value);
            
                    $updated_values = [
                        'bynder_multi_img' => $new_value_array,
                        'bynder_isMain' => $this->determineMediaType($image_value),
                        'use_bynder_cdn' => 1
                    ];
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        $updated_values,
                        $storeId
                    );
                } else {
                    $new_image_array = explode("\n", $img_json);
                    $new_alttext_array = explode("\n", $img_alt_text);
                    $new_magento_role_option_array = $mg_img_role_option;
                    $image_detail = [];
            
                    foreach ($new_image_array as $vv => $new_image_value) {
                        if (trim($new_image_value) != "" && $new_image_value != "no image") {
                            $img_altText_val = "";
                            if (isset($new_alttext_array[$vv])) {
                                if ($new_alttext_array[$vv] != "###" && strlen(trim($new_alttext_array[$vv])) > 0) {
                                    $img_altText_val = $new_alttext_array[$vv];
                                }
                            }
                            $curt_img_role = [];
                            if ($new_magento_role_option_array[$vv] != "###") {
                                $curt_img_role = $new_magento_role_option_array[$vv];
                            }
                            $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                            $alias_identifier = isset($byn_all_alias_identifier[$vv]) ? $byn_all_alias_identifier[$vv] : (isset($byn_all_alias_identifier[0]) ? $byn_all_alias_identifier[0] : "");
            
                            $find_video = strpos($new_image_value, "@@");
                            if (!$find_video) {
                                $image_detail[] = [
                                    "item_url" => $new_image_value,
                                    "alt_text" => $img_altText_val,
                                    "image_role" => $curt_img_role,
                                    "item_type" => 'IMAGE',
                                    "thum_url" => $new_image_value,
                                    "bynder_md_id" => $bynder_media_id[$vv],
                                    "is_import" => 0,
                                    "is_order" => $is_order,
                                    "all_alias_identifier" => $alias_identifier
                                ];
                            }
                            $total_new_value = count($image_detail);
                            if ($total_new_value > 1) {
                                foreach ($image_detail as $nn => $n_img) {
                                    if ($n_img['item_type'] == "IMAGE" && $nn != ($total_new_value - 1)) {
                                        if ($new_magento_role_option_array[$vv] != "###") {
                                            $new_mg_role_array = (array)$new_magento_role_option_array[$vv];
                                            if (count($n_img["image_role"]) > 0 && count($new_mg_role_array) > 0) {
                                                $result_val = array_diff($n_img["image_role"], $new_mg_role_array);
                                                $image_detail[$nn]["image_role"] = $result_val;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
            
                    $replacementRoles = ["Base", "Small", "Swatch", "Thumbnail"];
                    $flags = true;
                    foreach ($image_detail as &$item) {
                        if (in_array('Base', $item['image_role'])) {
                            $flags = false;
                        }
                    }
                    foreach ($image_detail as &$item) {
                        if ($flags && isset($item['image_role']) && is_array($item['image_role'])) {
                            $containsPlaceholder = in_array("###\n", $item['image_role']);
                            $hasAllReplacementRoles = empty(array_diff($replacementRoles, $item['image_role']));
                            if ($hasAllReplacementRoles) { break; }
                            if ($containsPlaceholder && !$hasAllReplacementRoles) {
                                $item['image_role'] = $replacementRoles;
                            }
                        }
                    }
                    foreach ($image_detail as &$items) {
                        if (isset($items['image_role']) && is_array($items['image_role'])) {
                            $items['image_role'] = array_values(array_filter(
                                $items['image_role'],
                                fn($role) => trim($role) !== '###'
                            ));
                        }
                    }
                    unset($items);

                    $merged_items = [];
                    $log_data = [];
                    foreach ($image_detail as $new_item) {
                        $item_url = $new_item['item_url'] ?? '';
                        if ($item_url !== '') {
                            $log_data[] = $new_item['item_url'];
                            $merged_items[] = $new_item;

                        }
                    }
                    $image_value = [];
                    if (!empty($merged_items)) {
                        $image_value[$alias_key] = $merged_items;
                    }
                    $new_value_array = json_encode($image_value, true);
                    $this->setAttributeDataCache($_product, 'bynder_multi_img', $image_value);
                    $updated_values = [
                        'bynder_multi_img' => $new_value_array,
                        'bynder_isMain' => $this->determineMediaType($image_value),
                        'use_bynder_cdn' => 1
                    ];
                    
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        $updated_values,
                        $storeId
                    );
                }
                $log_value_array = json_encode($log_data, true);
                $insert_data = [
                    "sku" => $product_sku_key . " alias sku " . $alias_key,
                    "message" => $log_value_array,
                    "data_type" => "1",
                    "lable" => 1
                ];
            $this->getInsertDataTable($insert_data);
            } elseif ($select_attribute == "video") {
                $videovalue = $this->getExistingAttributeData($_product, 'bynder_multi_img', $storeId);
                
                if (!empty($videovalue)) {
                    $new_video_array = explode("\n", $img_json);
                    $old_value_array = $videovalue[$product_sku_key];
                    $old_item_url = [];
                    $old_image_details = [];
                    if (!empty($old_value_array)) {
                        foreach ($old_value_array as $value) {
                            if ($value['item_type'] == 'VIDEO') {
                                $old_item_url[] = $value['item_url'];
                            }
                        }
                    }
                    $video_detail = [];
                    foreach ($new_video_array as $vv => $video_value) {
                        $item_url = explode("@@", $video_value);
                        $thum_url = explode("@@", $video_value);
                        $media_video_explode = explode("/", $item_url[0]);
                        $alias_identifier = isset($byn_all_alias_identifier[$vv]) ? $byn_all_alias_identifier[$vv] : (isset($byn_all_alias_identifier[0]) ? $byn_all_alias_identifier[0] : "");
                        $find_video = strpos($video_value, "@@");
                        if ($find_video) {
                            if (!in_array($item_url[0], $old_item_url)) {
                                $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                                $video_detail[] = [
                                    "item_url" => $item_url[0],
                                    "image_role" => null,
                                    "item_type" => 'VIDEO',
                                    "thum_url" => $thum_url[1],
                                    "bynder_md_id" => $bynder_media_id[$vv],
                                    "is_order" => $is_order,
                                    "all_alias_identifier" => $alias_identifier
                                ];
                            }
                        }
                    }
                    $existing_items = [];
                    if (isset($videovalue[$alias_key]) && is_array($videovalue[$alias_key])) {
                        $existing_items = $videovalue[$alias_key];
                    } elseif (isset($videovalue[$product_sku_key]) && is_array($videovalue[$product_sku_key])) {
                        $existing_items = $videovalue[$product_sku_key];
                    }

                    $merged_items = $existing_items;
                    foreach ($video_detail as $new_item) {
                        $item_url = $new_item['item_url'] ?? '';
                        $log_data[] = $new_item['item_url']; // Collect log data
                        $is_duplicate = false;
                        foreach ($merged_items as $existing_item) {
                            if (($existing_item['item_url'] ?? '') === $item_url) {
                                $is_duplicate = true;
                                break;
                            }
                        }
                        if (!$is_duplicate && $item_url !== '') {
                            $merged_items[] = $new_item;
                        }
                    }

                    if (!empty($merged_items)) {
                        $videovalue[$alias_key] = $merged_items;
                    } else {
                        unset($videovalue[$alias_key]);
                    }
                    
                    $new_value_array = json_encode($videovalue, true);
                    $this->setAttributeDataCache($_product, 'bynder_multi_img', $videovalue);
                    $updated_values = [
                        'bynder_multi_img' => $new_value_array,
                        'bynder_isMain' => $this->determineMediaType($videovalue),
                        'use_bynder_cdn' => 1
                    ];
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        $updated_values,
                        $storeId
                    );
                } else {
                    $new_video_array = explode("\n", $img_json);
                    $video_detail = [];
                    foreach ($new_video_array as $vv => $video_value) {
                        $find_video = strpos($video_value, "@@");
                        if ($find_video) {
                            $item_url = explode("@@", $video_value);
                            $thum_url = explode("@@", $video_value);
                            $media_video_explode = explode("/", $item_url[0]);
                            $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                            $alias_identifier = isset($byn_all_alias_identifier[$vv]) ? $byn_all_alias_identifier[$vv] : (isset($byn_all_alias_identifier[0]) ? $byn_all_alias_identifier[0] : "");
                            $video_detail[] = [
                                "item_url" => $item_url[0],
                                "image_role" => null,
                                "item_type" => 'VIDEO',
                                "thum_url" => $thum_url[1],
                                "bynder_md_id" => $bynder_media_id[$vv],
                                "is_order" => $is_order,
                                "all_alias_identifier" => $alias_identifier
                            ];
                        }
                    }
                    
                    $videoData = [];
                    $merged_items = [];
                    foreach ($video_detail as $new_item) {
                        $item_url = $new_item['item_url'] ?? '';
                        if ($item_url !== '') {
                            $log_data[] = $new_item['item_url']; // Collect log data
                            $merged_items[] = $new_item;
                        }
                    }
                    
                    $video_value = [];
                    if (!empty($merged_items)) {
                        $video_value[$alias_key] = $merged_items;
                    }
                    $new_value_array = json_encode($image_value, true);
                    $this->setAttributeDataCache($_product, 'bynder_multi_img', $video_value);
                    // if (!empty($video_detail)) {
                    //     $videoData[$alias_key] = $video_detail;
                    // }
                    
                    //$new_value_array = json_encode($videoData, true);
                    $updated_values = [
                        'bynder_multi_img' => $new_value_array,
                        'bynder_isMain' => $this->determineMediaType($video_value),
                        'use_bynder_cdn' => 1
                    ];
                    $this->productAction->updateAttributes(
                        [$product_ids],
                        $updated_values,
                        $storeId
                    );
                }
                $log_value_array = json_encode($log_data, true);
                $insert_data = [
                    "sku" => $product_sku_key . " alias sku " . $alias_key,
                    "message" => $log_value_array,
                    "data_type" => "2",
                    "lable" => 1
                ];
            } elseif ($select_attribute == "all_attribute") {
                $image_value = $this->getExistingAttributeData($_product, 'bynder_multi_img', $storeId);
                
                // Initialize separate log arrays for different types
                $log_images = [];
                $log_videos = [];
                $log_documents = [];
            
                $alias_key = isset($byd_alias_sku[0]) ? $byd_alias_sku[0] : $product_sku_key;
                $skuExistingItems = isset($image_value[$alias_key]) ? $image_value[$alias_key] : [];
                $all_item_url = [];
                $all_video_url = [];
                
                if (count($skuExistingItems) > 0) {
                    foreach ($skuExistingItems as $img) {
                        if ($img['item_type'] == 'IMAGE') {
                            $all_item_url[] = $img['item_url'];
                        } elseif ($img['item_type'] == 'VIDEO') {
                            $all_video_url[] = $img['item_url'];
                        }
                    }
                }
                
                $new_image_array = explode("\n", $img_json);
                $new_alttext_array = explode("\n", $img_alt_text);
                $new_magento_role_option_array = $mg_img_role_option;
                $image_detail = [];
                $video_detail = [];
                
                foreach ($new_image_array as $vv => $new_image_value) {
                    if (trim($new_image_value) != "" && $new_image_value != "no image") {
                        $item_url = explode("?", $new_image_value);
                        $media_image_explode = explode("/", $item_url[0]);
                        $img_altText_val = "";
                        if (isset($new_alttext_array[$vv])) {
                            if ($new_alttext_array[$vv] != "###" && strlen(trim($new_alttext_array[$vv])) > 0) {
                                $img_altText_val = $new_alttext_array[$vv];
                            }
                        }
                        $curt_img_role = [];
                        if ($new_magento_role_option_array[$vv] != "###") {
                            $curt_img_role = $new_magento_role_option_array[$vv];
                        }
                        $find_video = strpos($new_image_value, "@@");
                        $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                        $alias_sku = isset($byd_alias_sku[$vv]) ? $byd_alias_sku[$vv] : "";
                        $alias_identifier = isset($byn_all_alias_identifier[$vv]) ? $byn_all_alias_identifier[$vv] : (isset($byn_all_alias_identifier[0]) ? $byn_all_alias_identifier[0] : "");
                        
                        if (!$find_video) {
                            $image_detail[] = [
                                "item_url" => $new_image_value,
                                "alt_text" => $img_altText_val,
                                "image_role" => $curt_img_role,
                                "item_type" => 'IMAGE',
                                "thum_url" => $item_url[0],
                                "bynder_md_id" => $bynder_media_id[$vv],
                                "is_import" => 0,
                                "is_order" => $is_order,
                                "all_alias_identifier" => $alias_identifier
                            ];
                            
                            // Collect log data for images (data_type = 1)
                            $log_images[] = $new_image_value;
                            
                            $total_new_values = count($image_detail);
                            if ($total_new_values > 1) {
                                foreach ($image_detail as $nn => $n_img) {
                                    if ($n_img['item_type'] == "IMAGE" && $nn != ($total_new_values - 1)) {
                                        if ($new_magento_role_option_array[$vv] != "###") {
                                            $new_mg_role_array = (array)$new_magento_role_option_array[$vv];
                                            if (count($n_img["image_role"])>0 && count($new_mg_role_array)>0) {
                                                $result_val=array_diff($n_img["image_role"], $new_mg_role_array);
                                                $image_detail[$nn]["image_role"] = $result_val;
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            $item_url_video = explode("?", $new_image_value);
                            $thum_url = explode("@@", $new_image_value);
                            if (!empty($new_image_value)) {
                                $video_detail[] = [
                                    "item_url" => $item_url_video[0],
                                    "image_role" => null,
                                    "item_type" => 'VIDEO',
                                    "thum_url" => $thum_url[1],
                                    "bynder_md_id" => $bynder_media_id[$vv],
                                    "is_order" => $is_order,
                                    "all_alias_identifier" => $alias_identifier
                                ];
                                
                                // Collect log data for videos (data_type = 2)
                                $log_videos[] = $item_url_video[0];
                            }
                        }
                    }
                }
                
                // Get documents from the bynder_document attribute
                $doc_value = $this->getExistingAttributeData($_product, 'bynder_document', $storeId);
                $document_detail = [];
                
                if (!empty($doc_value) && isset($doc_value[$product_sku_key])) {
                    $existingDocItems = $doc_value[$product_sku_key];
                    
                    // Get existing documents from the database to preserve them
                    $all_doc_url = [];
                    if (count($existingDocItems) > 0) {
                        foreach ($existingDocItems as $doc) {
                            if ($doc['item_type'] == 'DOCUMENT') {
                                $all_doc_url[] = $doc['item_url'];
                            }
                        }
                    }
                    
                    foreach ($existingDocItems as $doc_item) {
                        if ($doc_item['item_type'] == 'DOCUMENT') {
                            $document_detail[] = [
                                "item_url" => $doc_item['item_url'],
                                "doc_name" => $doc_item['doc_name'] ?? '',
                                "item_type" => 'DOCUMENT',
                                "bynder_md_id" => $doc_item['bynder_md_id'] ?? '',
                                "is_order" => $doc_item['is_order'] ?? '',
                                "all_alias_identifier" => $doc_item['all_alias_identifier'] ?? ''
                            ];
                            
                            // Collect log data for documents (data_type = 3)
                            $log_documents[] = $doc_item['item_url'];
                        }
                    }
                }
                
                $replacementRoles = ["Base", "Small", "Swatch", "Thumbnail"];
                $flags = true;
                foreach ($image_detail as &$item) {
                    if (in_array('Base', $item['image_role'])) {
                        $flags = false;
                    }
                }
                foreach ($image_detail as &$item) {
                    if ($flags && isset($item['image_role']) && is_array($item['image_role'])) {
                        $containsPlaceholder = in_array("###\n", $item['image_role']);
                        $hasAllReplacementRoles = empty(array_diff($replacementRoles, $item['image_role']));
                        if ($hasAllReplacementRoles) { break; }
                        if ($containsPlaceholder && !$hasAllReplacementRoles) {
                            $item['image_role'] = $replacementRoles;
                        }
                    }
                }
                foreach ($image_detail as &$items) {
                    if (isset($items['image_role']) && is_array($items['image_role'])) {
                        $items['image_role'] = array_values(array_filter(
                            $items['image_role'],
                            fn($role) => trim($role) !== '###'
                        ));
                    }
                }
                unset($items);
                
                // Merge all media types
                $all_media_merge = array_merge($image_detail, $video_detail, $document_detail);
                $existing_items = [];
                if (isset($image_value[$alias_key]) && is_array($image_value[$alias_key])) {
                    $existing_items = $image_value[$alias_key];
                }
            
                $merged_items = $existing_items;
                foreach ($all_media_merge as $new_item) {
                    $item_url = $new_item['item_url'] ?? '';
                    $is_duplicate = false;
                    foreach ($merged_items as $existing_item) {
                        if (($existing_item['item_url'] ?? '') === $item_url) {
                            $is_duplicate = true;
                            break;
                        }
                    }
                    if (!$is_duplicate && $item_url !== '') {
                        $merged_items[] = $new_item;
                    }
                }
            
                if (!empty($merged_items)) {
                    $image_value[$alias_key] = $merged_items;
                } else {
                    unset($image_value[$alias_key]);
                }
                
                $new_value_array = json_encode($image_value, true);
                $this->setAttributeDataCache($_product, 'bynder_multi_img', $image_value);
                
                $updated_values = [
                    'bynder_multi_img' => $new_value_array,
                    'bynder_isMain' => $this->determineMediaType($image_value),
                    'use_bynder_cdn' => 1
                ];
                $this->productAction->updateAttributes(
                    [$product_ids],
                    $updated_values,
                    $storeId
                );
                
                // Insert separate logs for each type
                // Log for Images (data_type = 1)
                if (!empty($log_images)) {
                    $log_value_array = json_encode($log_images, true);
                    $insert_data = [
                        "sku" => $product_sku_key . " alias sku " . $alias_key,
                        "message" => $log_value_array,
                        "data_type" => "1", // 1 = Image
                        "lable" => 1
                    ];
                    $this->getInsertDataTable($insert_data);
                }
                
                // Log for Videos (data_type = 2)
                if (!empty($log_videos)) {
                    $log_value_array = json_encode($log_videos, true);
                    $insert_data = [
                        "sku" => $product_sku_key . " alias sku " . $alias_key,
                        "message" => $log_value_array,
                        "data_type" => "2", // 2 = Video
                        "lable" => 1
                    ];
                    $this->getInsertDataTable($insert_data);
                }
                
                // Log for Documents (data_type = 3)
                if (!empty($log_documents)) {
                    $log_value_array = json_encode($log_documents, true);
                    $insert_data = [
                        "sku" => $product_sku_key . " alias sku " . $alias_key,
                        "message" => $log_value_array,
                        "data_type" => "3", // 3 = Document
                        "lable" => 1
                    ];
                    $this->getInsertDataTable($insert_data);
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            return $result->setData(['message' => $e->getMessage()]);
        }
    }

    /**
     * Read the latest attribute value directly from the database.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attributeCode
     * @param int $storeId
     * @return array
     */
    protected function getExistingAttributeData($product, $attributeCode, $storeId)
    {
        if (!$product || !$product->getId()) {
            return [];
        }

        $cacheKey = $product->getId() . ':' . $attributeCode;
        if (array_key_exists($cacheKey, $this->attributeDataCache)) {
            return $this->attributeDataCache[$cacheKey];
        }

        $resource = $product->getResource();
        $value = $resource->getAttributeRawValue($product->getId(), $attributeCode, $storeId);

        if (empty($value)) {
            $this->attributeDataCache[$cacheKey] = [];
            return [];
        }

        $decoded = is_array($value) ? $value : json_decode($value, true);
        $result = is_array($decoded) ? $decoded : [];
        $this->attributeDataCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Cache the latest attribute value for the current request.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param string $attributeCode
     * @param array $value
     * @return void
     */
    protected function setAttributeDataCache($product, $attributeCode, $value)
    {
        if (!$product || !$product->getId()) {
            return;
        }

        $cacheKey = $product->getId() . ':' . $attributeCode;
        $this->attributeDataCache[$cacheKey] = is_array($value) ? $value : [];
    }

    /**
     * Determine media type based on images
     *
     * @param array $data
     * @return int
     */
    protected function determineMediaType($data)
    {
        $hasImage = false;
        $hasVideo = false;
        
        if (!is_array($data)) {
            return 0;
        }
        
        foreach ($data as $sku => $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (isset($item['item_type'])) {
                    if ($item['item_type'] == 'IMAGE') {
                        $hasImage = true;
                    } elseif ($item['item_type'] == 'VIDEO') {
                        $hasVideo = true;
                    }
                }
            }
        }
        
        if ($hasImage && $hasVideo) {
            return 1;
        } elseif ($hasImage) {
            return 2;
        } elseif ($hasVideo) {
            return 3;
        }
        return 0;
    }
    protected function getApiData($aliasSku, $all_alias_identifier, $property_id, $collection_value, $select_attribute, $collection_slug_val, $sku)
    {
        $bd_sku = trim(preg_replace('/[^A-Za-z0-9-]/', '_', $aliasSku));
                                
        $get_data = $this->datahelper->getImageSyncWithProperties($bd_sku, $property_id, $collection_value);
        $getIsJson = $this->getIsJSON($get_data);
        
        if (!empty($get_data) && $getIsJson) {
            $respon_array = json_decode($get_data, true);
        
            if ($respon_array['status'] == 1) {
                $convert_array = json_decode($respon_array['data'], true);
                if ($convert_array['status'] == 1) {
                    
                    $current_sku = $sku; // Original SKU
                    try {
                        // Pass the specific all_alias_identifier for this alias SKU
                        $this->getDataItem(
                            $select_attribute,
                            $convert_array,
                            $collection_slug_val,
                            $current_sku,
                            $aliasSku, // The alias SKU
                            $all_alias_identifier // The specific identifier for this SKU
                        );
                    } catch (Exception $e) {
                        $insert_data = [
                            "sku" => $sku ." alias sku ". $aliasSku,
                            "message" => $e->getMessage(),
                            "data_type" => "",
                            "lable" => "0"
                        ];
                        $this->getInsertDataTable($insert_data);
                    }
                } else {
                    $insert_data = [
                        "sku" => $sku ." alias sku ". $aliasSku,
                        "message" => $convert_array['data'],
                        "data_type" => "",
                        "lable" => "0"
                    ];
                    $this->getInsertDataTable($insert_data);
                    $product_id = $this->product->getIdBySku($sku);
                    $updated_values = [
                        'bynder_multi_img' => null,
                        'bynder_isMain' => null
                    ];
                    $storeId = $this->storeManagerInterface->getStore()->getId();
                    $this->productAction->updateAttributes(
                        [$product_id],
                        $updated_values,
                        $storeId
                    );
                }
            } else {
                $insert_data = [
                    "sku" => $sku,
                    "message" => 'Please Select The Metaproperty First.....',
                    "data_type" => "",
                    "lable" => "0"
                ];
                $this->getInsertDataTable($insert_data);
                $result_data = $result->setData(
                    ['status' => 0, 'message' => 'Please check AHF Synchronization. Action Log.....']
                );
                return $result_data;
            }
        } else {
            $result_data = $result->setData(
                [
                    'status' => 0,
                    'message' => 'Something went wrong from API side, Please contact to support team!'
                ]
            );
            return $result_data;
        }
    }
}