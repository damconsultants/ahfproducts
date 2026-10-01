<?php

namespace DamConsultants\Ahfproducts\Cron;

use Exception;
use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use Magento\Framework\Lock\LockManagerInterface;
use DamConsultants\Ahfproducts\Model\BynderFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;

class AutoAddFromMagento
{
    /**
     * Default number of SKUs fetched and processed per batch
     * (used when "Auto Product SKU Limit" config is empty).
     */
    const BATCH_SIZE = 20;

    /**
     * Lock name that stops two runs of this cron overlapping.
     */
    const LOCK_NAME = 'damconsultants_ahfproducts_auto_add';

    /**
     * Chunk size used when resetting bynder_auto_replace at the end of a cycle.
     */
    const RESET_CHUNK_SIZE = 500;

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
     * @var $_bynderAutoReplaceData
     */
    protected $_bynderAutoReplaceData;
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
     * @var $bynder
     */
    protected $bynder;
    /**
     * @var $_resource
     */
    protected $_resource;
    /**
     * @var LockManagerInterface
     */
    protected $lockManager;
    /**
     * @var array
     */
    protected $attributeDataCache = [];

    /**
     * @var array|null Per-SKU log buffer (one log row per SKU)
     */
    protected $skuLogBuffer = null;

    /**
     * Auto Add From Magento
     * @param LoggerInterface $logger
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Ahfproducts\Helper\Data $DataHelper
     * @param \DamConsultants\Ahfproducts\Model\BynderAutoReplaceDataFactory $bynderAutoReplaceData
     * @param \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param Action $action
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param BynderFactory $bynder
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Ahfproducts\Helper\Data $DataHelper,
        \DamConsultants\Ahfproducts\Model\BynderAutoReplaceDataFactory $bynderAutoReplaceData,
        \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        Action $action,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        BynderFactory $bynder,
        \Magento\Framework\App\ResourceConnection $resource,
        LockManagerInterface $lockManager
    ) {
        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->_bynderAutoReplaceData = $bynderAutoReplaceData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->bynder = $bynder;
        $this->_resource = $resource;
        $this->lockManager = $lockManager;
    }

    /**
     * Execute
     *
     * Processes ALL pending product SKUs in one run, fetching and syncing them
     * in batches of "Auto Product SKU Limit" (default BATCH_SIZE = 20).
     * When nothing is pending, resets bynder_auto_replace so the next run
     * starts a new cycle (same as the original behaviour).
     *
     * @return boolean
     */
    public function execute()
    {
        $this->logger->info("Auto Add Image Value");
        $enable = $this->datahelper->getAutoCronEnable();
        if (!$enable) {
            return false;
        }

        // Stop a second run from starting while a long run is still going.
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            $this->logger->info('Bynder auto add cron: previous run still in progress, skipping.');
            return false;
        }

        try {
            // Config value is the batch size; all pending SKUs are processed.
            $batchSize = (int)$this->datahelper->getAutoProductSkuLimitConfig();
            if ($batchSize <= 0) {
                $batchSize = self::BATCH_SIZE;
            }

            $property_id = null;
            $collection = $this->metaPropertyCollectionFactory->create()->getData();
            $meta_properties = $this->getMetaPropertiesCollection($collection);
            $collection_value = $meta_properties['collection_data_value'];
            $collection_slug_val = $meta_properties['collection_data_slug_val'];

            $lastEntityId = 0;
            $processed = 0;
            $batchNo = 0;

            // Keep fetching batches until no pending products are left.
            while (true) {
                $batch = $this->getProductBatch($lastEntityId, $batchSize);
                if (empty($batch)) {
                    break;
                }
                $batchNo++;

                foreach ($batch as $row) {
                    // Move the cursor forward even if this SKU fails,
                    // so a bad SKU can never block the rest of the run.
                    $lastEntityId = (int)$row['entity_id'];
                    $processed++;

                    $sku = trim((string)($row['sku'] ?? ''));
                    if ($sku === '') {
                        continue;
                    }

                    $this->startSkuLog($sku);
                    try {
                        $this->processSku($sku, $property_id, $collection_value, $collection_slug_val);
                    } catch (\Throwable $e) {
                        $this->logger->error('Bynder auto add cron SKU ' . $sku . ': ' . $e->getMessage());
                        $this->getInsertDataTable([
                            'sku' => $sku,
                            'alias_sku' => null,
                            'message' => $e->getMessage(),
                            'media_id' => '',
                            'data_type' => ''
                        ]);
                    }
                    $this->flushSkuLog();

                    // Make sure the product leaves the "bynder_auto_replace IS NULL"
                    // filter even when the DAM failed or returned nothing (2 = failed),
                    // so it is not picked up again in this cycle.
                    $this->markAutoReplace($sku, 2, true);
                }

                // Free memory between batches.
                $this->attributeDataCache = [];
                if (method_exists($this->_productRepository, 'cleanCache')) {
                    $this->_productRepository->cleanCache();
                }

                $this->logger->info(sprintf(
                    'Bynder auto add cron: batch %d done (%d SKUs processed, last entity_id %d)',
                    $batchNo,
                    $processed,
                    $lastEntityId
                ));
            }

            // Nothing was pending at the start of this run: start a new cycle.
            if ($batchNo === 0) {
                $this->resetAutoReplace();
            }
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }

        return true;
    }

    /**
     * Fetch the next batch of pending products after the given entity id.
     *
     * @param int $lastEntityId
     * @param int $batchSize
     * @return array
     */
    protected function getProductBatch($lastEntityId, $batchSize)
    {
        $product_collection = $this->collectionFactory->create();
        $product_collection->addAttributeToSelect('sku')
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_multi_img', 'notnull' => true]
                ]
            )
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_auto_replace', 'null' => true]
                ]
            )
            ->addAttributeToFilter('type_id', ['neq' => "configurable"])
            ->addFieldToFilter('entity_id', ['gt' => (int)$lastEntityId]);
        $product_collection->getSelect()
            ->reset(\Magento\Framework\DB\Select::ORDER)
            ->order('e.entity_id ASC')
            ->limit((int)$batchSize);

        return $product_collection->getData();
    }

    /**
     * Clear bynder_auto_replace on all enabled, visible products (new cycle).
     *
     * @return void
     */
    protected function resetAutoReplace()
    {
        $product_collection = $this->collectionFactory->create()
            ->addAttributeToFilter('visibility', \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_auto_replace', 'notnull' => true]
                ]
            );
        $ids = $product_collection->getAllIds();
        if (empty($ids)) {
            return;
        }

        $storeId = $this->storeManagerInterface->getStore()->getId();
        foreach (array_chunk($ids, self::RESET_CHUNK_SIZE) as $chunk) {
            $this->action->updateAttributes(
                $chunk,
                ['bynder_auto_replace' => ""],
                $storeId
            );
        }
        $this->logger->info("Bynder Auto Replace Attribute Null (" . count($ids) . " products reset)");
    }

    /**
     * Replace the Bynder data of one product SKU from the DAM.
     *
     * @param string $sku
     * @param mixed $property_id
     * @param array $collection_value
     * @param array $collection_slug_val
     * @return void
     */
    protected function processSku($sku, $property_id, $collection_value, $collection_slug_val)
    {
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $_product = $this->_productRepository->get($sku, false, $storeId, true);
        $product_ids = $_product->getId();
        $bynder_multi_img = $_product->getBynderMultiImg();
        $bynder_doc = $_product->getBynderDocument();
        if (!empty($bynder_multi_img)) {
            $this->action->updateAttributes(
                [$product_ids],
                [
                    'bynder_multi_img' => null,
                    'bynder_auto_replace' => null
                ],
                $storeId
            );
        }
        if (!empty($bynder_doc)) {
            $this->action->updateAttributes(
                [$product_ids],
                [
                    'bynder_document' => null,
                    'bynder_auto_replace' => null
                ],
                $storeId
            );
        }

        $aliasSku = $this->datahelper->getSkuByAlias($sku);
        $is_sku_made_alias = 0;
        if ($aliasSku === null || empty($aliasSku)) {
            $aliasSku = [
                [
                    'alias_sku' => $sku,
                    'all_alias_identifier' => $sku
                ]
            ];
            $is_sku_made_alias = 1;
        } elseif (!is_array($aliasSku) || !isset($aliasSku[0]) || !is_array($aliasSku[0])) {
            $is_sku_made_alias = 1;
            $aliasSku = [
                [
                    'alias_sku' => $this->normalizeStringValue($aliasSku),
                    'all_alias_identifier' => $this->normalizeStringValue($aliasSku)
                ]
            ];
        }

        if ($is_sku_made_alias == 0) {
            $this->setSkuLogAliases($sku, array_map(function ($a) {
                return is_array($a) ? ($a['alias_sku'] ?? '') : $a;
            }, $aliasSku));
        }

        $all_alias_identifier_value = '';
        foreach ($aliasSku as $a_sku) {
            $alias_sku_value = $this->normalizeStringValue($a_sku['alias_sku'] ?? $a_sku);
            $all_alias_identifier_value = $this->normalizeStringValue($a_sku['all_alias_identifier'] ?? '');
            if ($alias_sku_value === '') {
                $alias_sku_value = $this->normalizeStringValue($sku);
            }
            if ($all_alias_identifier_value === '') {
                $all_alias_identifier_value = $alias_sku_value;
            }

            // Log the alias only when it is a real alias (not the SKU itself).
            $this->syncFromDam(
                $sku,
                $alias_sku_value,
                $is_sku_made_alias ? null : $alias_sku_value,
                $all_alias_identifier_value,
                $property_id,
                $collection_value,
                $collection_slug_val
            );
        }

        if ($is_sku_made_alias == 0) {
            $this->syncFromDam(
                $sku,
                $sku,
                null,
                $all_alias_identifier_value,
                $property_id,
                $collection_value,
                $collection_slug_val
            );
        }
    }

    /**
     * Call the DAM for one lookup SKU and push the result to the product.
     *
     * @param string $sku               Magento product SKU
     * @param string $lookupSku         SKU/alias sent to the DAM
     * @param string|null $logAliasSku  alias_sku value written to the log table
     * @param string $all_alias_identifier_value
     * @param mixed $property_id
     * @param array $collection_value
     * @param array $collection_slug_val
     * @return void
     */
    protected function syncFromDam(
        $sku,
        $lookupSku,
        $logAliasSku,
        $all_alias_identifier_value,
        $property_id,
        $collection_value,
        $collection_slug_val
    ) {
        $bd_sku = trim((string) preg_replace('/[^A-Za-z0-9-]/', '_', $lookupSku));
        $get_data = $this->datahelper->getImageSyncWithProperties($bd_sku, $property_id, $collection_value);

        $insert_data = [
            'sku' => $sku,
            'alias_sku' => $logAliasSku,
            'message' => '',
            'media_id' => '',
            'data_type' => ''
        ];

        if (empty($get_data) || !$this->getIsJSON($get_data)) {
            $insert_data['message'] = "Something problem in DAM side please contact to developer.";
            $this->getInsertDataTable($insert_data);
            return;
        }

        $respon_array = json_decode($get_data, true);
        if ($respon_array['status'] != 1) {
            $insert_data['message'] = 'Please Select The Metaproperty First.....';
            $this->getInsertDataTable($insert_data);
            return;
        }

        $convert_array = json_decode($respon_array['data'], true);
        if ($convert_array['status'] != 1) {
            $insert_data['message'] = $convert_array['data'];
            $this->datahelper->updateIsSync($sku, 1);
            $this->getInsertDataTable($insert_data);
            return;
        }

        try {
            $this->getDataItem(
                $convert_array,
                $collection_slug_val,
                $sku,
                $lookupSku,
                $all_alias_identifier_value
            );
            $this->datahelper->updateIsSync($sku, 1);
        } catch (Exception $e) {
            $insert_data['message'] = $e->getMessage();
            $this->datahelper->updateIsSync($sku, 1);
            $this->getInsertDataTable($insert_data);
        }
    }

    /**
     * Set bynder_auto_replace on a product.
     *
     * @param string $sku
     * @param int $value
     * @param bool $onlyIfEmpty  do not overwrite an existing value (e.g. 1 = replaced)
     * @return void
     */
    protected function markAutoReplace($sku, $value, $onlyIfEmpty = false)
    {
        try {
            $storeId = $this->getMyStoreId();
            $_product = $this->_productRepository->get($sku, false, $storeId, true);
            $current = $_product->getData('bynder_auto_replace');
            if ($onlyIfEmpty && $current !== null && $current !== '') {
                return;
            }
            $this->action->updateAttributes(
                [$_product->getId()],
                ['bynder_auto_replace' => $value],
                $storeId
            );
        } catch (\Throwable $e) {
            $this->logger->error('Bynder auto add cron: could not set bynder_auto_replace for ' . $sku . ': ' . $e->getMessage());
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
        $this->logger->info("getMetaPropertiesCollection");
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
     * Is int
     *
     * @return $this
     */
    public function getMyStoreId()
    {
        $storeId = $this->storeManagerInterface->getStore()->getId();
        return $storeId;
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return $this
     */
    public function getIsJSON($string)
    {
        return ((json_decode($string)) === null) ? false : true;
    }
    /**
     * Alias SKU value for the log table: empty when the lookup was the product
     * SKU itself (no real alias), otherwise the alias that was used.
     *
     * @param string $sku
     * @param mixed $aliasSku
     * @return string|null
     */
    protected function getLogAliasSku($sku, $aliasSku)
    {
        $alias = trim($this->normalizeStringValue($aliasSku));
        if ($alias === '' || strcasecmp($alias, trim((string)$sku)) === 0) {
            return null;
        }
        return $alias;
    }
    /**
     * Collect every log entry of the SKU being synced and write ONE row per SKU.
     *
     * @return void
     */
    protected function startSkuLog($sku)
    {
        $this->skuLogBuffer = [
            'sku' => (string)$sku,
            'aliases' => [],
            'urls' => [],
            'media_ids' => [],
            'data_aliases' => [],
            'types' => [],
            'errors' => []
        ];
    }

    /**
     * Remember the real aliases of the SKU (never the SKU itself).
     *
     * @param string $sku
     * @param array $aliases
     * @return void
     */
    protected function setSkuLogAliases($sku, array $aliases)
    {
        if ($this->skuLogBuffer === null) {
            return;
        }
        foreach ($aliases as $alias) {
            $alias = trim($this->normalizeStringValue($alias));
            if ($alias !== '' && strcasecmp($alias, trim((string)$sku)) !== 0) {
                $this->skuLogBuffer['aliases'][$alias] = $alias;
            }
        }
    }

    /**
     * Add one log entry to the SKU buffer.
     *
     * @param array $insert_data
     * @return void
     */
    protected function bufferSkuLog(array $insert_data)
    {
        $type = trim((string)($insert_data['data_type'] ?? ''));
        $message = $insert_data['message'] ?? '';
        if (in_array($type, ['1', '2', '3'], true)) {
            $urls = is_string($message) ? json_decode($message, true) : $message;
            if (!is_array($urls)) {
                $urls = [$message];
            }
            foreach ($urls as $url) {
                $url = trim($this->normalizeStringValue($url));
                if ($url !== '') {
                    $this->skuLogBuffer['urls'][$url] = $url;
                }
            }
            foreach (explode(',', (string)($insert_data['media_id'] ?? '')) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $this->skuLogBuffer['media_ids'][$id] = $id;
                }
            }
            $this->skuLogBuffer['types'][$type] = $type;
            // Remember the alias only when the data really came from an alias.
            $entryAlias = trim($this->normalizeStringValue($insert_data['alias_sku'] ?? ''));
            if ($entryAlias !== '' && strcasecmp($entryAlias, $this->skuLogBuffer['sku']) !== 0) {
                $this->skuLogBuffer['data_aliases'][$entryAlias] = $entryAlias;
            }
        } else {
            $msg = trim($this->normalizeStringValue($message));
            if ($msg !== '') {
                $this->skuLogBuffer['errors'][$msg] = $msg;
            }
        }
    }

    /**
     * Write the single log row for the SKU: all URLs if anything was found,
     * otherwise the error message(s).
     *
     * @return void
     */
    protected function flushSkuLog()
    {
        $buf = $this->skuLogBuffer;
        $this->skuLogBuffer = null;
        if ($buf === null) {
            return;
        }

        if (!empty($buf['urls'])) {
            // Data found: show only the alias(es) that returned it (empty if it came from the SKU itself).
            $alias = implode(', ', $buf['data_aliases']);
            $types = $buf['types'];
            $type = isset($types['1']) ? '1' : (isset($types['3']) ? '3' : '2');
            $row = [
                'sku' => $buf['sku'],
                'alias_sku' => $alias,
                'message' => json_encode(array_values($buf['urls'])),
                'data_type' => $type,
                'media_id' => implode(',', $buf['media_ids']),
                'remove_for_magento' => '1',
                'added_on_cron_compactview' => '1',
                'lable' => 1
            ];
        } elseif (!empty($buf['errors'])) {
            // Nothing found: show the real aliases that were tried.
            $alias = implode(', ', $buf['aliases']);
            $row = [
                'sku' => $buf['sku'],
                'alias_sku' => $alias,
                'message' => implode(' | ', $buf['errors']),
                'data_type' => '',
                'media_id' => '',
                'remove_for_magento' => '',
                'added_on_cron_compactview' => '',
                'lable' => 0
            ];
        } else {
            return;
        }

        try {
            $this->writeLogRow($row);
        } catch (\Throwable $e) {
            $this->logger->error('Bynder cron: could not write log for ' . $buf['sku'] . ': ' . $e->getMessage());
        }
    }

    /**
     * Log entry point: buffered while a SKU is being synced, written directly otherwise.
     *
     * @param array $insert_data
     * @return void
     */
    public function getInsertDataTable($insert_data)
    {
        if ($this->skuLogBuffer !== null
            && (string)($insert_data['sku'] ?? '') === $this->skuLogBuffer['sku']) {
            $this->bufferSkuLog($insert_data);
            return;
        }
        $this->writeLogRow($insert_data);
    }

    /**
     * Save one row to the log table.
     *
     * @param array $insert_data
     * @return void
     */
    protected function writeLogRow($insert_data)
    {
        $model = $this->_bynderAutoReplaceData->create();
        $data_image_data = [
            'sku' => $insert_data['sku'],
            'alias_sku' => $this->getLogAliasSku($insert_data['sku'], $insert_data['alias_sku']),
            'bynder_data' =>$insert_data['message'],
            'media_id' => $insert_data['media_id'],
            'bynder_data_type' => $insert_data['data_type']
        ];

        $model->setData($data_image_data);
        $model->save();
    }
    /**
     * Normalize a value to a string.
     *
     * @param mixed $value
     * @return string
     */
    protected function normalizeStringValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $normalized = $this->normalizeStringValue($item);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
            return '';
        }

        if ($value === null) {
            return '';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return is_scalar($value) ? (string) $value : '';
    }
    /**
     * Is Json
     *
     * @param string $product_sku_key
     * @param array $m_id
     * @param string $product_ids
     * @param string $storeId
     * @return $this
     */
    public function getInsertMedaiDataTable($product_sku_key, $m_id, $product_ids, $storeId)
    {
        $model = $this->bynderMediaTable->create();
        $modelcollection = $this->bynderMediaTableCollectionFactory->create();
        $modelcollection->addFieldToFilter('sku', ['eq' => [$product_sku_key]])->load();
        $table_m_id = [];
        if (!empty($modelcollection)) {
            foreach ($modelcollection as $mdata) {
                $table_m_id[] = $mdata['media_id'];
            }
        }
        $media_diff = array_diff($m_id, $table_m_id);
        foreach ($media_diff as $new_data) {
            $data_image_data = [
                'sku' => $product_sku_key,
                'media_id' => trim($new_data),
                'status' => "1",
            ];
            $model->setData($data_image_data);
            $model->save();
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
    /**
     * Is Json
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
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     */
    public function getDataItem($convert_array, $collection_data_slug_val, $current_sku, $alias_sku = '', $all_alias_identifier = '')
    {
        $data_arr = [];
        $data_val_arr = [];
        $alias_sku = $this->normalizeStringValue($alias_sku);
        $all_alias_identifier = $this->normalizeStringValue($all_alias_identifier);
        if ($convert_array['status'] == 1) {

            foreach ($convert_array['data'] as $data_value) {
                $is_order = array();
                $bynder_media_id = $data_value['id'];
                $image_data = $data_value['thumbnails'];
                $bynder_image_role = $image_data['magento_role_options'];
                $bynder_alt_text = $image_data['img_alt_text'];
                $sku_slug_name = "property_" . $collection_data_slug_val['sku']['bynder_property_slug'];
                $data_sku[0] = $current_sku;
                /*Below code for multiple derivative according to image roll */
                $images_urls_list = [];
                $new_magento_role_list = [];
                $new_bynder_alt_text =[];
                $new_bynder_mediaid_text = [];
                $new_image_role = [];
                if (count($bynder_image_role) > 0) {
                    foreach ($bynder_image_role as $m_bynder_role) {
                        if (!empty($m_bynder_role)) {
                            //$new_image_role = ['Base', 'Small', 'Thumbnail', 'Swatch'];
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
                            /*$new_bynder_alt_text[] = (strlen($alt_text_vl) > 0)?$alt_text_vl."\n":"###\n";*/
                            $new_bynder_mediaid_text[] = $bynder_media_id;
                            $magento_order_slug = $collection_data_slug_val['image_order']['bynder_property_slug'];
                            if(isset($data_value[$magento_order_slug])) {
                                if(count($data_value[$magento_order_slug]) > 0) {
                                    foreach ($data_value[$magento_order_slug]  as $property_Magento_Media_Order) {
                                        $is_order[] = $property_Magento_Media_Order . "\n";
                                    }
                                }
                            }
                        } else {
                            if($data_value["is_base"] == 0){
                                $new_magento_role_list[] = "###"."\n";
                            }else{
                                $new_magento_role_list = ['Base', 'Small', 'Thumbnail', 'Swatch'];
                            }
                            /* this part added because sometime role not avaiable but alt text will be there*/
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
                                    foreach ($data_value[$magento_order_slug]  as $property_Magento_Media_Order) {
                                        $is_order[] = $property_Magento_Media_Order . "\n";
                                    }
                                }
                            }
                        }
                    }
                    $is_order = array_unique($is_order);
                } else {
                    // No roles from the DAM: use is_base, same as the empty-role branch above.
                    if (!empty($data_value['is_base'])) {
                        $new_magento_role_list = ['Base', 'Small', 'Thumbnail', 'Swatch'];
                    } else {
                        $new_magento_role_list[] = "###"."\n";
                    }
                    /* this part added because sometime role not avaiable but alt text will be there*/
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
                            foreach ($data_value[$magento_order_slug]  as $property_Magento_Media_Order) {
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
                                break; // take the first available public_url
                            }
                        }
                    }
                    /*$image_link = isset($data_value['derivatives'][0]['public_url']) ? $data_value['derivatives'][0]['public_url'] : $data_value['derivatives'][1]['public_url'];*/
                    array_push($data_arr, $data_sku[0]);
                    $data_p = [
                        "sku" => $data_sku[0],
                        "url" => [$image_link."\n"], /* chagne by kuldip ladola for testing perpose */
                        'magento_image_role' => $new_magento_role_list,
                        'image_alt_text' => $new_bynder_alt_text,
                        'bynder_media_id_new' => $new_bynder_mediaid_text,
                        "type" => "image",
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
                                    break; // take the first available public_url
                                } else {
                                    $video_link = $derivative['s3_link'] . '@@' . $derivative['main_link'];
                                }

                            }
                        }
                        //$video_link = $image_data['s3_link'] . '@@' . $data_value['derivatives'][0]['original_link'];
                        array_push($data_arr, $data_sku[0]);
                        $data_p = [
                            "sku" => $data_sku[0],
                            "url" => [$video_link. "\n"],
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
                        $doc_name = $data_value["name"];
                        $doc_name_with_space = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
                        $doc_link = "";
                        if (!empty($data_value['derivatives']) && is_array($data_value['derivatives'])) {
                            foreach ($data_value['derivatives'] as $derivative) {
                                if (isset($derivative['public_url']) && !empty($derivative['public_url'])) {
                                    $doc_link = $derivative['public_url'] . '@@' . $doc_name . "\n";
                                    break; // take the first available public_url
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
                                "type" => "document",
                                'is_order' => $is_order,
                                'alias_sku' => $alias_sku,
                                'all_alias_identifier' => $all_alias_identifier
                            ];
                            array_push($data_val_arr, $data_p);
                        }
                    }

                }
            }
        }
        if (count($data_arr) > 0) {
            $this->getProcessItem($data_arr, $data_val_arr);
        }
    }
    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     */
    public function getProcessItem($data_arr, $data_val_arr)
    {
        $image_value_details_role = [];
        $temp_arr = [];
        $byn_is_order = [];
        $types = [];
        $alias_sku = [];
        $all_alias_identifier = [];
        foreach ($data_arr as $key => $skus) {
            $alias_value = isset($data_val_arr[$key]['alias_sku']) ? $data_val_arr[$key]['alias_sku'] : '';
            $group_key = $skus;
            if (!empty($alias_value)) {
                $group_key = $skus . '||' . $alias_value;
            }

            $temp_arr[$group_key][] =  implode("", $data_val_arr[$key]["url"]);
            $image_value_details_role[$group_key][] = $data_val_arr[$key]["magento_image_role"];
            $image_alt_text[$group_key][] = implode("", $data_val_arr[$key]["image_alt_text"]);
            $byn_md_id_new[$group_key][] = implode("", $data_val_arr[$key]["bynder_media_id_new"]);
            $types[$group_key][] = $data_val_arr[$key]['type'];
            $byn_is_order[$group_key][] = implode("", $data_val_arr[$key]["is_order"]);
            $alias_sku[$group_key][] = $alias_value;
            $all_alias_identifier[$group_key][] = isset($data_val_arr[$key]['all_alias_identifier']) ? $data_val_arr[$key]['all_alias_identifier'] : '';
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
            $group_types = isset($types[$group_key]) ? array_unique($types[$group_key]) : [];
            $group_alias_sku = $alias_sku[$group_key] ?? [];
            $group_alias_identifier = $all_alias_identifier[$group_key] ?? [];
            $this->getUpdateImage(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $byn_md_id_new[$group_key] ?? [],
                $group_types,
                $byd_media_is_order,
                $group_alias_sku,
                $group_alias_identifier
            );
        }
    }
    public function getUpdateImage($img_json, $product_sku_key, $mg_img_role_option, $img_alt_text, $bynder_media_ids, $types, $byd_media_is_order, $byd_alias_sku = [], $byd_all_alias_identifier = [])
    {
        $image_detail = [];
        $video_detail = [];
        $log_images = [];
        $log_videos = [];
        // Default so the document branch and the catch block never hit an undefined variable.
        $alias_key = !empty($byd_alias_sku)
            ? $this->normalizeStringValue($byd_alias_sku)
            : $product_sku_key;

        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key, false, $storeId, true);
            $product_ids = $_product->getId();
            $cacheKey = $_product->getId() . ':bynder_multi_img';
            $image_value = $this->attributeDataCache[$cacheKey] ?? [];
            $auto_replace = $_product->getBynderAutoReplace();

            if (empty($image_value)) {
                $existingData = $_product->getBynderMultiImg();
                if (!empty($existingData)) {
                    $image_value = is_array($existingData) ? $existingData : json_decode($existingData, true);
                    if (!is_array($image_value)) {
                        $image_value = [];
                    }
                }
            }

            $doc_value = $_product->getBynderDocument();
            $bynder_media_id = [];
            if (isset($bynder_media_ids[$product_sku_key]) && is_array($bynder_media_ids[$product_sku_key])) {
                $bynder_media_id = $bynder_media_ids[$product_sku_key];
            } elseif (is_array($bynder_media_ids)) {
                $bynder_media_id = $bynder_media_ids;
            }
            $isOrder = explode("\n", $byd_media_is_order);

            // Get all alias keys from the existing image_value
            $existing_alias_keys = array_keys($image_value);

            // Get all alias SKUs from the input
            $alias_keys = !empty($byd_alias_sku) ? (is_array($byd_alias_sku) ? $byd_alias_sku : [$byd_alias_sku]) : [$product_sku_key];

            // Check if there are any new alias SKUs that need to be processed
            $new_alias_skus = array_diff($alias_keys, $existing_alias_keys);

            // Condition: Process if auto_replace is null OR if there are new alias SKUs
            $should_process = ($auto_replace == null) || !empty($new_alias_skus);

            if (in_array("image", $types) || in_array("video", $types)) {
                if ($should_process) {
                    $new_image_array = explode("\n", $img_json);
                    $new_alttext_array = explode("\n", $img_alt_text);
                    $new_magento_role_option_array = $mg_img_role_option;

                    foreach ($new_image_array as $vv => $image_item) {
                        if (trim($image_item) != "" && $image_item != "no image") {
                            $img_altText_val = "";
                            if (isset($new_alttext_array[$vv])) {
                                if ($new_alttext_array[$vv] != "###" && strlen(trim($new_alttext_array[$vv])) > 0) {
                                    $img_altText_val = $new_alttext_array[$vv];
                                }
                            }
                            $curt_img_role = [];
                            if (isset($new_magento_role_option_array[$vv]) && $new_magento_role_option_array[$vv] != "###") {
                                $curt_img_role = is_array($new_magento_role_option_array[$vv])
                                    ? $new_magento_role_option_array[$vv]
                                    : [$new_magento_role_option_array[$vv]];
                            }
                            $find_video = strpos($image_item, "@@");
                            $find_doc = strpos($image_item, "??");

                            if (!$find_video && !$find_doc) {
                                $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                                $image_detail[] = [
                                    "item_url" => $image_item,
                                    "alt_text" => $img_altText_val,
                                    "image_role" => $curt_img_role,
                                    "item_type" => 'IMAGE',
                                    "thum_url" => $image_item,
                                    "bynder_md_id" => $bynder_media_id[$vv],
                                    "is_import" => 0,
                                    "is_order" => $is_order,
                                    "all_alias_identifier" => $this->normalizeStringValue($byd_all_alias_identifier[$vv] ?? $byd_all_alias_identifier[0] ?? '')
                                ];
                                $log_images[] = $image_item;

                            } elseif($find_video) {
                                $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                                $item_url = explode("@@", $image_item);
                                $thum_url = explode("@@", $image_item);
                                $media_video_explode = explode("/", $item_url[0]);

                                $video_detail[] = [
                                    "item_url" => $item_url[0],
                                    "image_role" => null,
                                    "item_type" => 'VIDEO',
                                    "thum_url" => $thum_url[1],
                                    "bynder_md_id" => $bynder_media_id[$vv],
                                    "is_order" => $is_order,
                                    "all_alias_identifier" => $this->normalizeStringValue($byd_all_alias_identifier[$vv] ?? $byd_all_alias_identifier[0] ?? '')
                                ];
                                $log_videos[] = $item_url[0];
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

                    // If the DAM did not mark any image as Base, give the roles to the
                    // FIRST image by media order (lowest is_order), like the manual sync,
                    // instead of the last image.
                    $allRoles = ['Base', 'Small', 'Thumbnail', 'Swatch'];
                    $hasBase = false;
                    foreach ($image_detail as $chk) {
                        if (in_array('Base', (array)$chk['image_role'], true)) {
                            $hasBase = true;
                            break;
                        }
                    }
                    if (!$hasBase && !empty($image_detail)) {
                        $baseIndex = null;
                        $bestOrder = null;
                        foreach ($image_detail as $idx => $chk) {
                            $ord = trim((string)($chk['is_order'] ?? ''));
                            $ord = is_numeric($ord) ? (float)$ord : PHP_INT_MAX;
                            if ($baseIndex === null || $ord < $bestOrder) {
                                $baseIndex = $idx;
                                $bestOrder = $ord;
                            }
                        }
                        // Roles already used by other images stay with them.
                        $taken = [];
                        foreach ($image_detail as $idx => $chk) {
                            if ($idx !== $baseIndex) {
                                foreach ((array)$chk['image_role'] as $r) {
                                    $taken[trim((string)$r)] = true;
                                }
                            }
                        }
                        $roles = ['Base'];
                        foreach ($allRoles as $r) {
                            if ($r !== 'Base' && !isset($taken[$r])) {
                                $roles[] = $r;
                            }
                        }
                        $image_detail[$baseIndex]['image_role'] = $roles;
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

                    $marge = array_merge($image_detail, $video_detail);
                    $m_id = [];
                    $type = [];
                    foreach ($marge as $img) {
                        $type[] = $img['item_type'];
                        $m_id[] = $img['bynder_md_id'];
                        $this->getDeleteMedaiDataTable($product_sku_key, $img['bynder_md_id']);
                    }
                    $this->getInsertMedaiDataTable($product_sku_key, $m_id, $product_ids, $storeId);

                    $flag = 0;
                    if (in_array("IMAGE", $type) && in_array("VIDEO", $type)) {
                        $flag = 1;
                    } elseif (in_array("IMAGE", $type)) {
                        $flag = 2;
                    } elseif (in_array("VIDEO", $type)) {
                        $flag = 3;
                    }

                    // Determine which alias keys to process
                    if ($auto_replace == null) {
                        // First run: Process ALL alias SKUs
                        $process_alias_keys = $alias_keys;
                    } else {
                        // Subsequent runs: Process ONLY new alias SKUs
                        $process_alias_keys = $new_alias_skus;
                    }

                    // Process each alias key
                    foreach ($process_alias_keys as $alias_key) {
                        // Skip if alias key is empty
                        if (empty($alias_key)) {
                            continue;
                        }

                        $existing_items = [];
                        if (isset($image_value[$alias_key])) {
                            $existing_items = $image_value[$alias_key];
                            if (!is_array($existing_items)) {
                                $existing_items = [];
                            }
                        }

                        $merged_items = [];
                        foreach ($existing_items as $existing_item) {
                            if (is_array($existing_item)) {
                                $merged_items[] = $existing_item;
                            }
                        }

                        foreach ($marge as $new_item) {
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
                    }

                    // Only update if we processed something
                    if (!empty($process_alias_keys)) {
                        $new_value_array = json_encode($image_value, true);
                        $this->setAttributeDataCache($_product, 'bynder_multi_img', $image_value);

                        $updated_values = [
                            'bynder_multi_img' => $new_value_array,
                            'bynder_isMain' => $flag,
                            'bynder_auto_replace' => 1,
                            'use_bynder_cdn' => 1
                        ];
                        $this->action->updateAttributes(
                            [$product_ids],
                            $updated_values,
                            $storeId
                        );

                        // Insert logs only once per type
                        if (!empty($log_images)) {
                            $log_value_array = json_encode($log_images, true);
                            $insert_data = [
                                'sku' => $product_sku_key,
                                'alias_sku' => $alias_key,
                                'message' => $log_value_array,
                                'media_id' => implode(',', $m_id),
                                'data_type' => '1',
                            ];
                            $this->getInsertDataTable($insert_data);
                        }

                        if (!empty($log_videos)) {
                            $log_value_array = json_encode($log_videos, true);
                            $insert_data = [
                                'sku' => $product_sku_key,
                                'alias_sku' => $alias_key,
                                'message' => $log_value_array,
                                'media_id' => implode(',', $m_id),
                                'data_type' => '3',
                            ];
                            $this->getInsertDataTable($insert_data);
                        }
                    }
                }
            }

            if (in_array("document", $types)) {
                if(empty($doc_value)) {
                    $new_doc_array = explode("\n", $img_json);
                    $doc_detail = [];
                    $log_documents = [];

                    foreach ($new_doc_array as $vv => $doc_values) {
                        $find_doc = strpos($doc_values, "??");
                        if($find_doc) {
                            $item_url = explode("??", $doc_values);
                            $doc_name = explode("??", $doc_values);
                            $media_doc_explode = explode("/", $item_url[0]);
                            if(isset($doc_name[1]) && isset($bynder_media_id[$vv])){
                                $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : "";
                                $doc_detail[] = [
                                    "item_url" => $item_url[0],
                                    "item_type" => 'DOCUMENT',
                                    "doc_name" => $doc_name[1],
                                    "bynder_md_id" => $bynder_media_id[$vv],
                                    "is_order" => $is_order,
                                    "all_alias_identifier" => $this->normalizeStringValue($byd_all_alias_identifier[$vv] ?? $byd_all_alias_identifier[0] ?? '')
                                ];
                                $log_documents[] = $item_url[0];
                            }
                        }
                    }

                    if (!empty($doc_detail)) {
                        $new_value_array = json_encode($doc_detail, true);
                        $this->action->updateAttributes(
                            [$product_ids],
                            ['bynder_document' => $new_value_array, 'bynder_auto_replace' => 1],
                            $storeId
                        );

                        // Log documents
                        if (!empty($log_documents)) {
                            $log_value_array = json_encode($log_documents, true);
                            $insert_data = [
                                'sku' => $product_sku_key,
                                'alias_sku' => $alias_key,
                                'message' => $log_value_array,
                                'media_id' => '',
                                'data_type' => '2',
                            ];
                            $this->getInsertDataTable($insert_data);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $insert_data = [
                'sku' => $product_sku_key,
                'alias_sku' => $alias_key,
                "message" => $e->getMessage(),
                'media_id' => "",
                "data_type" => ""
            ];
            $this->getInsertDataTable($insert_data);
        }
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
}