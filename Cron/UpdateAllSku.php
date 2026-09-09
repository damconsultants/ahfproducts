<?php

namespace DamConsultants\Ahfproducts\Cron;

use Psr\Log\LoggerInterface;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MagentoSkuCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\MagentoSku;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Exception;

class UpdateAllSku
{
    /**
     * Queue row statuses
     */
    const SKU_STATUS_PENDING = 'pending';
    const SKU_STATUS_NO_DATA = 'no_data';
    const SKU_STATUS_FAILED  = 'failed';

    /**
     * Outcome of a single SKU / alias sync attempt
     */
    const RESULT_UPDATED = 'updated';   // data was written into the product attribute
    const RESULT_NO_DATA = 'no_data';   // API answered, but nothing to write
    const RESULT_FAILED  = 'failed';    // API error, bad payload or attribute write failed
    const RESULT_API_ERROR = 'api_error';
    const RESULT_RETRY   = 'retry';

    const FREQUENCY_MINUTES = 'E';
    const DEFAULT_SKU_LIMIT = 200;
    const DEFAULT_MIN_SKU_LIMIT = 10;
    const BATCH_SIZE = 15;          // rows held in memory per batch on D/W/M
    const QUEUE_ID_FIELD = 'id';     // primary key of the magento_sku queue table
    const LOCK_NAME = 'damconsultants_ahfproducts_update_all_sku';
    const MAX_RUN_SECONDS = 240;
    const MAX_DB_RETRIES = 3;
    const LOCK_WAIT_TIMEOUT = 15;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory = false;
    /**
     * @var $resultJsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var $productAction
     */
    protected $productAction;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
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
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $_byndersycData
     */
    protected $_byndersycData;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $product
     */
    protected $product;
    protected $resource;
    protected $magentoSkuCollectionFactory;
    protected $magentoSku;
    /**
     * @var LockManagerInterface
     */
    protected $lockManager;
    private array $attributeDataCache = [];

    /**
     * Product Sku.
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory $byndersycData
     * @param \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param MagentoSkuCollectionFactory $magentoSkuCollectionFactory
     * @param MagentoSku $magentoSku
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param \DamConsultants\Ahfproducts\Helper\Data $DataHelper
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param ResourceConnection $resource
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory $byndersycData,
        \DamConsultants\Ahfproducts\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        MagentoSkuCollectionFactory $magentoSkuCollectionFactory,
        MagentoSku $magentoSku,
        \Magento\Catalog\Model\Product $product,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        \DamConsultants\Ahfproducts\Helper\Data $DataHelper,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        ResourceConnection $resource,
        LockManagerInterface $lockManager
    ) {
        $this->resultJsonFactory = $jsonFactory;
        $this->productAction = $action;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->magentoSkuCollectionFactory = $magentoSkuCollectionFactory;
        $this->datahelper = $DataHelper;
        $this->magentoSku = $magentoSku;
        $this->_byndersycData = $byndersycData;
        $this->_productRepository = $productRepository;
        $this->product = $product;
        $this->resource = $resource;
        $this->lockManager = $lockManager;
    }

    /**
     * Execute
     *
     * @return boolean|\Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $enable = $this->datahelper->getUpdateSkuCronEnable();
        if (!$enable) {
            return false;
        }

        // timeout 0 = do not queue behind a running instance, just skip this tick.
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            return $this->resultJsonFactory->create()->setData([
                'status' => 0,
                'message' => 'Another UpdateAllSku run is still in progress; skipping this run.'
            ]);
        }

        try {
            return $this->runQueue();
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * Drain the pending queue
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function runQueue()
    {
        $enterMin = $this->datahelper->getUpdateSkuMin();
        $frequency = $this->datahelper->getUpdateSkuFrequency();
        $is_minute_schedule = ($frequency === self::FREQUENCY_MINUTES);

        if ($is_minute_schedule) {
            $batch_size = (int)$this->datahelper->getUpdateSkuLimitConfig();
        } else {
            $batch_size = self::BATCH_SIZE;
        }
        $this->applyLockWaitTimeout();

        $deadline = time() + self::MAX_RUN_SECONDS;

        $result = $this->resultJsonFactory->create();

        // Nothing pending at all -> start a fresh cycle over parked rows.
        $pending_check = $this->magentoSkuCollectionFactory->create()
            ->addFieldToFilter('status', self::SKU_STATUS_PENDING);

        if ($pending_check->getSize() === 0) {
            $requeued = $this->requeueUnprocessedSkus();

            if ($requeued > 0) {
                return $result->setData([
                    'status' => 0,
                    'message' => sprintf(
                        'No pending SKUs. %d SKU(s) with "no_data" / "failed" status were reset to '
                        . '"pending" and will be processed on the next run.',
                        $requeued
                    )
                ]);
            }

            return $result->setData(['status' => 0, 'message' => 'No pending SKUs to process.']);
        }

        $property_id = null;
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);
        $collection_value = $meta_properties['collection_data_value'];
        $collection_slug_val = $meta_properties['collection_data_slug_val'];

        $processed_count = 0;
        $retained_count = 0;
        $no_data_count = 0;
        $failed_count = 0;
        $stopped_early = false;
        $last_id = 0;

        while (true) {
            $skucollection = $this->magentoSkuCollectionFactory->create();
            $skucollection->addFieldToFilter('status', self::SKU_STATUS_PENDING)
                ->addFieldToFilter(self::QUEUE_ID_FIELD, ['gt' => $last_id])
                ->setOrder(self::QUEUE_ID_FIELD, \Magento\Framework\Data\Collection::SORT_ORDER_ASC)
                ->setPageSize($batch_size)
                ->setCurPage(1);

            if ($skucollection->getSize() === 0) {
                break;
            }
            
            foreach ($skucollection as $skuData) {
                
                $row_id = (int)$skuData->getData(self::QUEUE_ID_FIELD);
                if ($row_id > $last_id) {
                    $last_id = $row_id;
                }

                $sku = $skuData['sku'];
                if ($sku == "") {
                    $this->saveSkuReport($skuData, 'failed', 'SKU is empty');
                    continue;
                }

                $select_attribute = $skuData['select_attribute'];
                $select_store = $skuData['select_store'];

                try {
                    $product_id = $this->product->getIdBySku($sku);

                    if (!$product_id) {
                        $this->getInsertDataTable([
                            "sku" => $sku,
                            "alias_sku" => null,
                            "message" => "SKU not found in products",
                            "data_type" => "",
                            "sync_source" => "2",
                            "lable" => "0"
                        ]);
                        $this->saveSkuReport($skuData, 'failed', 'SKU not found in products');
                        $this->markSkuStatus($skuData, self::SKU_STATUS_FAILED);
                        $this->magentoSku->delete($skuData);
                        $failed_count++;
                        continue;
                    }
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $this->getInsertDataTable([
                        "sku" => $sku,
                        "alias_sku" => null,
                        "message" => "SKU not match in products",
                        "data_type" => "",
                        "sync_source" => "2",
                        "lable" => "0"
                    ]);
                    $this->saveSkuReport($skuData, 'failed', 'SKU not match in products');
                    $this->markSkuStatus($skuData, self::SKU_STATUS_FAILED);
                    $this->magentoSku->delete($skuData);
                    $failed_count++;
                    continue;
                }

                // Collect the outcome of every alias attempt for this queue row.
                $sync_results = [];

                // Clear the Bynder media attributes ONCE per queue row, before any
                // alias is processed. This used to happen inside processSku(), so
                // every alias iteration wiped what the previous alias had written
                // and only the last one (the parent SKU) survived in the JSON.
                $clear_result = $this->clearProductMediaAttributes($sku, $product_id);
                if ($clear_result !== null) {
                    $this->saveSkuReport(
                        $skuData,
                        'pending',
                        'Unable to reset Bynder media attributes; waiting for retry'
                    );
                    $retained_count++;
                    continue;
                }

                $aliasSku = $this->datahelper->getSkuByAlias($sku);
                $is_sku_made_alias = 0;
                if ($aliasSku === null || empty($aliasSku)) {
                    $is_sku_made_alias = 1;
                    $sync_results[] = $this->processSku(
                        $sku,
                        null,
                        $select_attribute,
                        $select_store,
                        $property_id,
                        $collection_value,
                        $collection_slug_val,
                        null,
                        $skuData
                    );
                } else {
                    foreach ($aliasSku as $a_sku) {
                        if ($a_sku['alias_sku'] == null || empty($a_sku['alias_sku'])) {
                            $is_sku_made_alias = 1;
                            $sync_results[] = $this->processSku(
                                $sku,
                                null,
                                $select_attribute,
                                $select_store,
                                $property_id,
                                $collection_value,
                                $collection_slug_val,
                                $a_sku['all_alias_identifier'] ?? null,
                                $skuData
                            );
                        } else {
                            $sync_results[] = $this->processSku(
                                $sku,
                                $a_sku['alias_sku'],
                                $select_attribute,
                                $select_store,
                                $property_id,
                                $collection_value,
                                $collection_slug_val,
                                $a_sku['all_alias_identifier'] ?? null,
                                $skuData
                            );
                        }
                    }
                }
                if ($is_sku_made_alias == 0) {
                    $all_alias_identifier = [];
                    $sync_results[] = $this->processSku(
                        $sku,
                        null,
                        $select_attribute,
                        $select_store,
                        $property_id,
                        $collection_value,
                        $collection_slug_val,
                        $all_alias_identifier,
                        $skuData
                    );
                }

                $has_retry     = in_array(self::RESULT_RETRY,     $sync_results, true);
                $has_api_error = in_array(self::RESULT_API_ERROR, $sync_results, true);
                $has_update    = in_array(self::RESULT_UPDATED,   $sync_results, true);
                $has_failure   = in_array(self::RESULT_FAILED,    $sync_results, true);
                $has_no_data   = in_array(self::RESULT_NO_DATA,   $sync_results, true);

                if ($has_retry) {
                    $this->saveSkuReport(
                        $skuData,
                        'pending',
                        'Database lock contention while writing product attributes; waiting for retry'
                    );
                    $retained_count++;
                } elseif ($has_api_error) {
                    // API side problem - keep the row "pending" so the next run retries it.
                    $this->saveSkuReport($skuData, 'pending', 'Bynder API or response error; waiting for retry');
                    $retained_count++;
                } elseif ($has_update && !$has_failure) {
                    $this->datahelper->updateIsSync($sku, 1);
                    $this->saveSkuReport($skuData, 'success', 'SKU synchronized successfully');
                    $this->magentoSku->delete($skuData);
                    $processed_count++;
                } elseif ($has_failure) {
                    // Write/attribute failure with a valid API answer - nothing to retry.
                    $this->saveSkuReport($skuData, 'failed', 'Product attribute synchronization failed');
                    $this->magentoSku->delete($skuData);
                    $failed_count++;
                } else {
                    // no_data, or no alias produced any result
                    $this->saveSkuReport($skuData, 'no_data', 'No Bynder data was available for synchronization');
                    $this->magentoSku->delete($skuData);
                    $no_data_count++;
                }
            }

            // Free the batch before loading the next one.
            $skucollection->clear();
            unset($skucollection);
            $this->attributeDataCache = [];

            // Every Minute: one batch per run, the schedule itself paces the work.
            if ($is_minute_schedule) {
                break;
            }

            // D/W/M: keep draining, but never run long enough to overlap the next
            // tick. Whatever is left is still "pending" and will be picked up.
            if (time() >= $deadline) {
                $stopped_early = true;
                break;
            }
        }

        return $result->setData([
            'status' => 1,
            'message' => sprintf(
                'Sync finished. %d SKU(s) completed and removed from queue, %d SKU(s) marked "no_data", '
                . '%d SKU(s) failed, %d SKU(s) kept as "pending" for retry.%s '
                . 'Please check Bynder Synchronization Log.',
                $processed_count,
                $no_data_count,
                $failed_count,
                $retained_count,
                $stopped_early ? ' Run time limit reached; remaining SKUs continue on the next run.' : ''
            )
        ]);
    }

    /**
     * Shorten the InnoDB lock wait for this connection only.
     *
     * With the 50s server default a single contended product blocked the whole
     * cron for the best part of a minute before throwing 1205. A short wait plus
     * bounded retries clears transient contention much faster.
     *
     * @return void
     */
    protected function applyLockWaitTimeout()
    {
        try {
            $this->resource->getConnection()->query(
                'SET SESSION innodb_lock_wait_timeout = ' . (int)self::LOCK_WAIT_TIMEOUT
            );
        } catch (Exception $e) {
            // Not fatal - the server default simply stays in force.
            $this->getInsertDataTable([
                "sku" => '',
                "alias_sku" => null,
                "message" => 'Unable to set innodb_lock_wait_timeout: ' . $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);
        }
    }

    /**
     * updateAttributes() with exponential backoff on transient InnoDB errors.
     *
     * 1205 (lock wait timeout) and 1213 (deadlock) are transient by definition:
     * the correct response is to wait and try again, not to log an error row and
     * drop the SKU. Only a non retryable error, or exhausting the attempts, is
     * allowed to escape to the caller.
     *
     * @param array $productIds
     * @param array $values
     * @param int $storeId
     * @return void
     * @throws \Exception
     */
    protected function updateProductAttributes(array $productIds, array $values, $storeId)
    {
        $attempt = 0;

        while (true) {
            try {
                $this->productAction->updateAttributes($productIds, $values, $storeId);
                return;
            } catch (\Exception $e) {
                $attempt++;
                if (!$this->isRetryableDbError($e) || $attempt >= self::MAX_DB_RETRIES) {
                    throw $e;
                }
                // 200ms, 400ms, 800ms plus jitter, so two writers that collided
                // do not immediately collide again on the retry.
                usleep((int)(200000 * pow(2, $attempt - 1)) + random_int(0, 100000));
            }
        }
    }

    /**
     * Is this a transient database error worth retrying?
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function isRetryableDbError(\Throwable $e)
    {
        $message = $e->getMessage();

        return stripos($message, 'Lock wait timeout exceeded') !== false
            || stripos($message, 'Deadlock found when trying to get lock') !== false
            || stripos($message, 'try restarting transaction') !== false;
    }

    /**
     * Reset every "no_data" / "failed" row back to "pending".
     *
     * Called only when the pending queue is empty, so the cron starts a fresh
     * cycle over the SKUs it previously parked. Uses one bulk UPDATE rather than
     * loading and saving each model, since this can touch the whole table.
     *
     * @return int Number of rows moved back to "pending"
     */
    protected function requeueUnprocessedSkus()
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->magentoSku->getMainTable();

            return (int)$connection->update(
                $table,
                ['status' => self::SKU_STATUS_PENDING],
                ['status IN (?)' => [self::SKU_STATUS_NO_DATA, self::SKU_STATUS_FAILED]]
            );
        } catch (Exception $e) {
            $this->getInsertDataTable([
                "sku" => '',
                "alias_sku" => null,
                "message" => 'Unable to requeue no_data/failed SKUs: ' . $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);
            return 0;
        }
    }

    /**
     * Persist a queue row result before the queue row is removed.
     */
    protected function saveSkuReport($skuData, $status, $message)
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('bynder_update_sku_report');
        $data = [
            'queue_id' => (int)$skuData->getData(self::QUEUE_ID_FIELD),
            'token' => (string)$skuData->getData('token'),
            'sku' => (string)$skuData->getData('sku'),
            'select_attribute' => (string)$skuData->getData('select_attribute'),
            'select_store' => (string)$skuData->getData('select_store'),
            'status' => $status,
            'message' => $message
        ];

        $connection->insertOnDuplicate(
            $table,
            $data,
            ['token', 'sku', 'select_attribute', 'select_store', 'status', 'message']
        );

        $token = (string)$skuData->getData('token');
        if ($token === '') {
            return;
        }

        $counts = $connection->fetchAll(
            $connection->select()
                ->from($table, ['status', 'total' => new \Zend_Db_Expr('COUNT(*)')])
                ->where('token = ?', $token)
                ->group('status')
        );
        $summary = [
            'success' => 0,
            'failed' => 0,
            'no_data' => 0,
            'pending' => 0
        ];
        foreach ($counts as $count) {
            if (isset($summary[$count['status']])) {
                $summary[$count['status']] = (int)$count['total'];
            }
        }

        $summaryTable = $this->resource->getTableName('bynder_update_sku_token');
        $totalSku = (int)$connection->fetchOne(
            $connection->select()->from($summaryTable, ['total_sku'])->where('token = ?', $token)
        );
        $isComplete = $totalSku > 0
            && $summary['pending'] === 0
            && ($summary['success'] + $summary['failed'] + $summary['no_data']) >= $totalSku;

        $connection->update(
            $summaryTable,
            [
                'success_count' => $summary['success'],
                'failed_count' => $summary['failed'],
                'no_data_count' => $summary['no_data'],
                'pending_count' => $summary['pending'],
                'status' => $isComplete ? 'complete' : 'pending'
            ],
            ['token = ?' => $token]
        );
    }

    /**
     * Reset the Bynder media attributes for one queue row.
     *
     * Must run exactly once per SKU, before the alias loop starts. Each alias
     * then merges into the same attribute value instead of overwriting it, so a
     * SKU that has aliases ends up as {"ALIAS":[...],"PARENT":[...]} rather than
     * only holding whichever alias happened to be processed last.
     *
     * @param string $sku
     * @param int $product_id
     * @return string|null RESULT_RETRY / RESULT_API_ERROR on failure, null on success
     */
    protected function clearProductMediaAttributes($sku, $product_id)
    {
        try {
            $_product = $this->_productRepository->get($sku);
            $storeId = $this->storeManagerInterface->getStore()->getId();

            // One write instead of two. Every updateAttributes() call is its own
            // transaction against catalog_product_entity, so halving them halves
            // the window in which another process can collide with this one.
            $clear_values = [];
            if (!empty($_product->getBynderMultiImg())) {
                $clear_values['bynder_multi_img'] = null;
            }
            if (!empty($_product->getBynderDocument())) {
                $clear_values['bynder_document'] = null;
            }

            if (empty($clear_values)) {
                return null;
            }

            $this->updateProductAttributes([$product_id], $clear_values, $storeId);

            // The attribute is NULL now, so the in-request cache must report
            // "empty" instead of still holding the pre-clear value.
            foreach (array_keys($clear_values) as $cleared_code) {
                $this->attributeDataCache[$product_id . ':' . $cleared_code] = [];
            }

            return null;
        } catch (Exception $e) {
            $retryable = $this->isRetryableDbError($e);

            $this->getInsertDataTable([
                "sku" => $sku,
                "alias_sku" => null,
                "message" => ($retryable
                        ? 'Transient database lock while clearing media attributes, will retry: '
                        : 'Unable to clear media attributes: ') . $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);

            return $retryable ? self::RESULT_RETRY : self::RESULT_API_ERROR;
        }
    }

    /**
     * Process Single SKU
     *
     * Returns one of self::RESULT_UPDATED / RESULT_NO_DATA / RESULT_FAILED /
     * RESULT_RETRY / RESULT_API_ERROR. The queue row is never deleted here - the
     * caller still owns the delete / retry decision.
     *
     * @param string $sku
     * @param string $aliasSku
     * @param string $select_attribute
     * @param string $select_store
     * @param string $property_id
     * @param array $collection_value
     * @param array $collection_slug_val
     * @param string $all_alias_identifier
     * @param \Magento\Framework\Model\AbstractModel|null $skuData Queue row model
     * @return string
     */
    protected function processSku(
        $sku,
        $aliasSku,
        $select_attribute,
        $select_store,
        $property_id,
        $collection_value,
        $collection_slug_val,
        $all_alias_identifier = null,
        $skuData = null
    ) {
        try {
            $bd_sku = trim(preg_replace('/[^A-Za-z0-9-]/', '_', $aliasSku ?? $sku));
            $get_data = $this->datahelper->getImageSyncWithProperties(
                $bd_sku,
                $property_id,
                $collection_value
            );

            if (empty($get_data) || !$this->getIsJSON($get_data)) {
                $this->getInsertDataTable([
                    "sku" => $sku,
                    "alias_sku" => $aliasSku,
                    "message" => "Something went wrong from API side, Please contact to support team!",
                    "data_type" => "",
                    "sync_source" => "2",
                    "lable" => "0"
                ]);
                return self::RESULT_API_ERROR;
            }

            $respon_array = json_decode($get_data, true);

            if (!is_array($respon_array) || ($respon_array['status'] ?? 0) != 1) {
                $this->getInsertDataTable([
                    "sku" => $sku,
                    "alias_sku" => $aliasSku,
                    "message" => 'Please Select The Metaproperty First.....',
                    "data_type" => "",
                    "sync_source" => "2",
                    "lable" => "0"
                ]);
                return self::RESULT_API_ERROR;
            }

            $convert_array = json_decode($respon_array['data'] ?? '', true);

            if (!is_array($convert_array) || ($convert_array['status'] ?? 0) != 1) {
                $this->getInsertDataTable([
                    "sku" => $sku,
                    "alias_sku" => $aliasSku,
                    "message" => is_array($convert_array)
                        ? (is_string($convert_array['data'] ?? null)
                            ? $convert_array['data']
                            : json_encode($convert_array['data'] ?? 'Invalid response payload'))
                        : 'Invalid response payload',
                    "data_type" => "",
                    "sync_source" => "2",
                    "lable" => "0"
                ]);
                return self::RESULT_NO_DATA;
            }

            // The Bynder media attributes are cleared once per queue row in
            // runQueue(), before the alias loop. Clearing them here as well made
            // every alias erase the JSON written by the previous alias, so the
            // attribute ended up holding only the last alias that was processed.

            // getDataItem writes to the product attributes and reports what happened.
            $sync_result = $this->getDataItem(
                $select_attribute,
                $convert_array,
                $collection_slug_val,
                $sku,
                $aliasSku,
                $all_alias_identifier
            );

            return $sync_result;
        } catch (Exception $e) {
            // A lock error thrown by an attribute write lands here. Report it as
            // retryable so the queue row survives.
            $retryable = $this->isRetryableDbError($e);

            $this->getInsertDataTable([
                "sku" => $sku,
                "alias_sku" => $aliasSku,
                "message" => ($retryable ? 'Transient database lock, will retry: ' : '') . $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);

            return $retryable ? self::RESULT_RETRY : self::RESULT_API_ERROR;
        }
    }

    /**
     * Set the status on a queue row so it is no longer picked up by the pending
     * filter, without losing the record.
     *
     * Accepts either the queue row model (preferred, no extra query) or a plain
     * SKU string, in which case every pending row for that SKU is resolved and
     * updated. Passing a string used to fatal with
     * "Call to a member function setData() on string".
     *
     * @param \Magento\Framework\Model\AbstractModel|string $skuData
     * @param string $status
     * @return bool
     */
    protected function markSkuStatus($skuData, $status)
    {
        $logSku = '';

        try {
            // Plain SKU string - resolve the queue row(s) first.
            if (!is_object($skuData)) {
                $logSku = (string)$skuData;
                if ($logSku === '') {
                    return false;
                }

                $rows = $this->magentoSkuCollectionFactory->create()
                    ->addFieldToFilter('sku', $logSku)
                    ->addFieldToFilter('status', self::SKU_STATUS_PENDING);

                $saved = false;
                foreach ($rows as $row) {
                    $row->setData('status', $status);
                    $this->magentoSku->save($row);
                    $saved = true;
                }
                return $saved;
            }

            $logSku = (string)$skuData->getData('sku');
            $skuData->setData('status', $status);
            $this->magentoSku->save($skuData);
            return true;
        } catch (Exception $e) {
            $this->getInsertDataTable([
                "sku" => $logSku,
                "alias_sku" => null,
                "message" => 'Unable to set queue status to "' . $status . '": ' . $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);
            return false;
        }
    }

    /**
     * Resolve the array key the media should be stored under.
     *
     * $byd_alias_sku always contains one entry per media row, and rows without
     * an alias contribute an empty string. isset() is true for '', so the old
     * `isset($byd_alias_sku[0]) ? ... : $product_sku_key` check never fell back
     * and non-aliased SKUs were written under a "" key. Pick the first entry
     * that is actually a non-empty alias, otherwise use the Magento SKU.
     *
     * @param array|string|null $byd_alias_sku
     * @param string $product_sku_key
     * @return string
     */
    protected function resolveAliasKey($byd_alias_sku, $product_sku_key)
    {
        foreach ((array)$byd_alias_sku as $candidate_alias) {
            if (is_string($candidate_alias) && trim($candidate_alias) !== '') {
                return trim($candidate_alias);
            }
        }

        return $product_sku_key;
    }

    /**
     * First non-empty alias, or null when the SKU has none. Used for log rows so
     * alias_sku is stored as NULL instead of an empty string.
     *
     * @param array|string|null $byd_alias_sku
     * @return string|null
     */
    protected function resolveLogAliasSku($byd_alias_sku)
    {
        foreach ((array)$byd_alias_sku as $candidate_alias) {
            if (is_string($candidate_alias) && trim($candidate_alias) !== '') {
                return trim($candidate_alias);
            }
        }

        return null;
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
            'alias_sku' => $insert_data['alias_sku'],
            'bynder_sync_data' => $insert_data['message'],
            'bynder_data_type' => $insert_data['data_type'],
            "sync_source" => $insert_data['sync_source'],
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
        try {
            $this->updateProductAttributes(
                [$product_ids],
                $updated_values,
                $storeId
            );
        } catch (Exception $e) {
            $insert_data = [
                "sku" => $sku,
                "alias_sku" => null,
                "message" => $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ];
            $this->getInsertDataTable($insert_data);
        }
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
        $model = $this->bynderMediaTableCollectionFactory->create();
        $model->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        foreach ($model as $mdata) {
            if ($mdata['media_id'] != $media_id) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();
            }
        }
    }

    /**
     * Get My Store Id
     *
     * @return array
     */
    public function getMyStoreId()
    {
        $storeIds = [];
        $stores = $this->storeManagerInterface->getStores();
        foreach ($stores as $store) {
            $storeIds[] = $store->getId();
        }
        return $storeIds;
    }

    /**
     * Get Data Item
     *
     * Returns one of self::RESULT_UPDATED / RESULT_NO_DATA / RESULT_FAILED /
     * RESULT_RETRY so the caller knows whether anything really landed in the
     * product attribute, and whether the failure is worth retrying.
     *
     * @param string $select_attribute
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     * @param string $alias_sku
     * @param string $all_alias_identifier
     * @return string
     */
    public function getDataItem($select_attribute, $convert_array, $collection_data_slug_val, $current_sku, $alias_sku, $all_alias_identifier)
    {
        $data_arr = [];
        $data_val_arr = [];
        $doc_data_arr = [];
        $doc_data = [];

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

        // Nothing usable came back for this SKU - do not report it as updated,
        // so the queue row is preserved.
        if (count($doc_data_arr) == 0 && count($data_arr) == 0) {
            $this->getInsertDataTable([
                "sku" => $current_sku,
                "alias_sku" => $alias_sku,
                "message" => 'No Data Found...',
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);
            return self::RESULT_NO_DATA;
        }

        $image_write = null;
        $doc_write = null;

        if (count($data_arr) > 0) {
            $image_write = $this->getProcessItem($data_arr, $data_val_arr);
        }
        if (count($doc_data_arr) > 0) {
            $doc_write = $this->getProcessItemDoc($doc_data_arr, $doc_data);
        }

        // Transient lock problem: nothing landed, but the SKU is still good.
        // Reported ahead of RESULT_FAILED so the caller keeps the queue row.
        if ($image_write === self::RESULT_RETRY || $doc_write === self::RESULT_RETRY) {
            return self::RESULT_RETRY;
        }

        // Any failed write means the SKU must stay "pending" for a retry.
        if ($image_write === self::RESULT_FAILED || $doc_write === self::RESULT_FAILED) {
            return self::RESULT_FAILED;
        }

        // Something reached the attribute -> updated. Otherwise the payload held
        // no usable media, which is a "no_data" outcome rather than a failure.
        if ($image_write === self::RESULT_UPDATED || $doc_write === self::RESULT_UPDATED) {
            return self::RESULT_UPDATED;
        }

        $this->getInsertDataTable([
            "sku" => $current_sku,
            "alias_sku" => $alias_sku,
            "message" => 'No Data Found...',
            "data_type" => "",
            "sync_source" => "2",
            "lable" => "0"
        ]);

        return self::RESULT_NO_DATA;
    }

    /**
     * Get Process Item
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @return string self::RESULT_UPDATED / RESULT_NO_DATA / RESULT_FAILED / RESULT_RETRY
     */
    public function getProcessItem($data_arr, $data_val_arr)
    {
        $image_value_details_role = [];
        $temp_arr = [];
        $byn_is_order = [];
        $alias_sku = [];
        $all_alias_identifier = [];
        $image_alt_text = [];
        $byn_md_id_new = [];

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
            $all_alias_identifier[$group_key][] = $data_val_arr[$key]["all_alias_identifier"];
        }

        $any_written = false;
        $any_failed = false;
        $any_retry = false;

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
            $written = $this->getUpdateImage(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $group_media_ids,
                $byd_media_is_order,
                $byd_alias_sku,
                $byn_all_alias_identifier
            );

            if ($written === self::RESULT_UPDATED) {
                $any_written = true;
            } elseif ($written === self::RESULT_RETRY) {
                $any_retry = true;
            } elseif ($written === self::RESULT_FAILED) {
                $any_failed = true;
            }
        }

        if ($any_retry) {
            return self::RESULT_RETRY;
        }

        if ($any_failed) {
            return self::RESULT_FAILED;
        }

        return $any_written ? self::RESULT_UPDATED : self::RESULT_NO_DATA;
    }

    /**
     * Get Process Item Document
     *
     * @param array $data_arr
     * @param array $data_val_arr
     * @return string self::RESULT_UPDATED / RESULT_NO_DATA / RESULT_FAILED / RESULT_RETRY
     */
    public function getProcessItemDoc($data_arr, $data_val_arr)
    {
        $image_value_details_role = [];
        $temp_arr = [];
        $byn_is_order = [];
        $alias_sku = [];
        $all_alias_identifier = [];
        $image_alt_text = [];
        $byn_md_id_new = [];

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
            $all_alias_identifier[$group_key][] = $data_val_arr[$key]["all_alias_identifier"];
        }

        $any_written = false;
        $any_failed = false;
        $any_retry = false;

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
            $written = $this->getUpdateDoc(
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $group_media_ids,
                $byd_media_is_order,
                $byd_alias_sku,
                $byn_all_alias_identifier
            );

            if ($written === self::RESULT_UPDATED) {
                $any_written = true;
            } elseif ($written === self::RESULT_RETRY) {
                $any_retry = true;
            } elseif ($written === self::RESULT_FAILED) {
                $any_failed = true;
            }
        }

        if ($any_retry) {
            return self::RESULT_RETRY;
        }

        if ($any_failed) {
            return self::RESULT_FAILED;
        }

        return $any_written ? self::RESULT_UPDATED : self::RESULT_NO_DATA;
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
     * @param string $byd_alias_sku
     * @param string $byn_all_alias_identifier
     * @return string self::RESULT_UPDATED / RESULT_NO_DATA / RESULT_FAILED / RESULT_RETRY
     */
    public function getUpdateDoc($img_json, $product_sku_key, $mg_img_role_option, $img_alt_text, $bynder_media_ids, $byd_media_is_order, $byd_alias_sku, $byn_all_alias_identifier)
    {
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();

            // Get existing bynder_document data from the database
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

            // Get alias key. Falls back to the Magento SKU when the row carries no
            // real alias, instead of the old empty-string key.
            $alias_key = $this->resolveAliasKey($byd_alias_sku, $product_sku_key);

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
                    $docData[$alias_key] = $doc_detail;
                }

                // Nothing valid to persist - not an error, just nothing to sync.
                if (empty($docData)) {
                    return self::RESULT_NO_DATA;
                }

                $new_value_array = json_encode($docData, true);

                $this->updateProductAttributes(
                    [$product_ids],
                    ['bynder_document' => $new_value_array],
                    $storeId
                );

                $this->setAttributeDataCache($_product, 'bynder_document', $docData);
            } else {
                $item_old_value = $doc_values;
                if (is_array($item_old_value)) {
                    $skuExistingItems = isset($item_old_value[$alias_key]) ? $item_old_value[$alias_key] : [];
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

                if (empty($doc_detail)) {
                    return self::RESULT_NO_DATA;
                }

                $existingGroupItems = isset($doc_values[$alias_key]) && is_array($doc_values[$alias_key])
                    ? $doc_values[$alias_key]
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
                    $doc_values[$alias_key] = $mergedDocItems;
                } else {
                    unset($doc_values[$alias_key]);
                }

                // Drop any legacy "" bucket left behind by the old alias handling.
                if (array_key_exists('', $doc_values)) {
                    unset($doc_values['']);
                }

                $new_value_array = json_encode($doc_values, true);
                $this->updateProductAttributes(
                    [$product_ids],
                    ['bynder_document' => $new_value_array],
                    $storeId
                );

                $this->setAttributeDataCache($_product, 'bynder_document', $doc_values);
            }

            // Insert log for documents (data_type = 3)
            if (!empty($log_documents)) {
                $log_value_array = json_encode($log_documents, true);
                $insert_data = [
                    "sku" => $product_sku_key,
                    "alias_sku" => $this->resolveLogAliasSku($byd_alias_sku),
                    "message" => $log_value_array,
                    "data_type" => "3",
                    "sync_source" => "2",
                    "lable" => 1
                ];
                $this->getInsertDataTable($insert_data);
            }

            return self::RESULT_UPDATED;
        } catch (\Exception $e) {
            // A lock wait timeout / deadlock is transient: report RESULT_RETRY so
            // the caller keeps the queue row pending instead of deleting the SKU.
            $retryable = $this->isRetryableDbError($e);

            $this->getInsertDataTable([
                "sku" => $product_sku_key,
                "alias_sku" => $this->resolveLogAliasSku($byd_alias_sku),
                "message" => ($retryable
                        ? 'Transient database lock on document attribute, will retry: '
                        : 'Document attribute update failed: ') . $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);

            return $retryable ? self::RESULT_RETRY : self::RESULT_FAILED;
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
     * @return string self::RESULT_UPDATED / RESULT_NO_DATA / RESULT_FAILED / RESULT_RETRY
     */
    public function getUpdateImage($img_json, $product_sku_key, $mg_img_role_option, $img_alt_text, $bynder_media_ids, $byd_media_is_order, $byd_alias_sku, $byn_all_alias_identifier)
    {
        $image_detail = [];
        $video_detail = [];
        $diff_image_detail = [];
        $log_data = [];
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();

            // Get existing bynder_multi_img data from the database
            $image_value = $this->getExistingAttributeData($_product, 'bynder_multi_img', $storeId);
            $doc_value = $_product->getBynderDocument();
            $bynder_media_id = [];
            if (isset($bynder_media_ids[$product_sku_key]) && is_array($bynder_media_ids[$product_sku_key])) {
                $bynder_media_id = $bynder_media_ids[$product_sku_key];
            } elseif (is_array($bynder_media_ids)) {
                $bynder_media_id = $bynder_media_ids;
            }
            // $byd_alias_sku holds one entry per media row and non-aliased rows
            // contribute ''. isset() is true for '', so the previous
            // `isset($byd_alias_sku[0]) ? ... : $product_sku_key` check never
            // reached its fallback and the JSON was keyed by "". Resolve the first
            // genuinely non-empty alias, otherwise fall back to the Magento SKU.
            $alias_key = $this->resolveAliasKey($byd_alias_sku, $product_sku_key);
            $isOrder = explode("\n", $byd_media_is_order);

            $new_image_array = explode("\n", $img_json);
            $new_alttext_array = explode("\n", $img_alt_text);
            $new_magento_role_option_array = $mg_img_role_option;

            // Only ever merge into this alias' own bucket. The old fallback to
            // $image_value[$product_sku_key] copied the parent SKU's media into
            // the alias bucket whenever the parent was written first.
            $existing_items = [];
            if (isset($image_value[$alias_key]) && is_array($image_value[$alias_key])) {
                $existing_items = $image_value[$alias_key];
            }

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
                    if (isset($new_magento_role_option_array[$vv]) && $new_magento_role_option_array[$vv] != "###") {
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
                            "bynder_md_id" => $bynder_media_id[$vv] ?? '',
                            "is_import" => 0,
                            "is_order" => $is_order,
                            "all_alias_identifier" => $alias_identifier
                        ];
                        $log_data[] = $new_image_value;

                        $total_new_values = count($image_detail);
                        if ($total_new_values > 1) {
                            foreach ($image_detail as $nn => $n_img) {
                                if ($n_img['item_type'] == "IMAGE" && $nn != ($total_new_values - 1)) {
                                    if (isset($new_magento_role_option_array[$vv]) && $new_magento_role_option_array[$vv] != "###") {
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

            // Nothing usable to persist - not an error, just nothing to sync.
            if (empty($image_detail)) {
                return self::RESULT_NO_DATA;
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
            unset($item);
            foreach ($image_detail as &$items) {
                if (isset($items['image_role']) && is_array($items['image_role'])) {
                    $items['image_role'] = array_values(array_filter(
                        $items['image_role'],
                        fn($role) => trim($role) !== '###'
                    ));
                }
            }
            unset($items);

            $merged_items = $existing_items;
            foreach ($image_detail as $new_item) {
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

            // Drop any legacy "" bucket written by the previous alias handling, so
            // a re-sync cleans the product up instead of leaving both keys.
            if (array_key_exists('', $image_value)) {
                unset($image_value['']);
            }

            $new_value_array = json_encode($image_value, true);
            $log_value_array = json_encode($log_data, true);

            $updated_values = [
                'bynder_multi_img' => $new_value_array,
                'bynder_isMain' => $this->determineMediaType($image_value),
                'use_bynder_cdn' => 1
            ];
            $this->updateProductAttributes(
                [$product_ids],
                $updated_values,
                $storeId
            );

            // Only cache the new value once the write actually went through.
            $this->setAttributeDataCache($_product, 'bynder_multi_img', $image_value);

            // Insert log for images
            if (!empty($log_data)) {
                $insert_data = [
                    "sku" => $product_sku_key,
                    "alias_sku" => $this->resolveLogAliasSku($byd_alias_sku),
                    "message" => $log_value_array,
                    "data_type" => "1",
                    "sync_source" => "2",
                    "lable" => 1
                ];
                $this->getInsertDataTable($insert_data);
            }

            return self::RESULT_UPDATED;
        } catch (\Exception $e) {
            // 1205 / 1213 are transient: keep the queue row so the next run retries
            // instead of deleting the SKU and losing it, which is what the old
            // RESULT_FAILED path did on every lock wait timeout.
            $retryable = $this->isRetryableDbError($e);

            $this->getInsertDataTable([
                "sku" => $product_sku_key,
                "alias_sku" => $this->resolveLogAliasSku($byd_alias_sku),
                "message" => ($retryable
                        ? 'Transient database lock on image attribute, will retry: '
                        : 'Image attribute update failed: ') . $e->getMessage(),
                "data_type" => "",
                "sync_source" => "2",
                "lable" => "0"
            ]);

            return $retryable ? self::RESULT_RETRY : self::RESULT_FAILED;
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
}