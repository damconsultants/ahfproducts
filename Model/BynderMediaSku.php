<?php

namespace DamConsultants\Ahfproducts\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Keeps the bynder_media_sku table (parent sku / child sku / media id / image url)
 * in step with the product attribute "bynder_multi_img".
 *
 * bynder_multi_img is stored as JSON keyed by child / alias SKU:
 *   {"CHILD-SKU-1": [{"bynder_md_id": "...", "item_url": "...", ...}], "CHILD-SKU-2": [...]}
 * Older data can also be a flat list of items; then the child SKU is the parent SKU.
 */
class BynderMediaSku
{
    public const TABLE = 'bynder_media_sku';
    public const ATTRIBUTE = 'bynder_multi_img';

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var ProductResource
     */
    protected $productResource;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ResourceConnection $resource
     * @param ProductResource $productResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resource,
        ProductResource $productResource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->productResource = $productResource;
        $this->logger = $logger;
    }

    /**
     * Sync table rows for product ids after bynder_multi_img was written.
     *
     * @param array $productIds
     * @param mixed $value  new bynder_multi_img value (JSON string, array or null)
     * @return void
     */
    public function syncProductIds(array $productIds, $value)
    {
        $productIds = array_filter(array_map('intval', $productIds));
        if (empty($productIds)) {
            return;
        }
        foreach ($this->productResource->getProductsSku($productIds) as $row) {
            if (!empty($row['sku'])) {
                $this->syncParentSku((string)$row['sku'], $value);
            }
        }
    }

    /**
     * Replace all rows of one parent SKU with what the attribute value holds.
     * Rows that still exist keep their original created_at.
     *
     * @param string $parentSku
     * @param mixed $value
     * @return void
     */
    public function syncParentSku($parentSku, $value)
    {
        $parentSku = trim((string)$parentSku);
        if ($parentSku === '') {
            return;
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $newRows = $this->extractRows($parentSku, $value);

        $existing = $connection->fetchAll(
            $connection->select()
                ->from($table, ['id', 'child_sku', 'media_id'])
                ->where('parent_sku = ?', $parentSku)
        );
        $deleteIds = [];
        foreach ($existing as $row) {
            $key = $this->rowKey($row['child_sku'], $row['media_id']);
            if (!isset($newRows[$key])) {
                $deleteIds[] = (int)$row['id'];
            }
        }
        if (!empty($deleteIds)) {
            $connection->delete($table, ['id IN (?)' => $deleteIds]);
        }
        if (!empty($newRows)) {
            // Unique key (parent_sku, child_sku, media_id): new rows are inserted,
            // existing rows only get their image_url refreshed.
            $connection->insertOnDuplicate($table, array_values($newRows), ['image_url']);
        }
    }

    /**
     * Remove every row of a parent SKU (product deleted).
     *
     * @param string $parentSku
     * @return void
     */
    public function deleteParentSku($parentSku)
    {
        $connection = $this->resource->getConnection();
        $connection->delete(
            $this->resource->getTableName(self::TABLE),
            ['parent_sku = ?' => (string)$parentSku]
        );
    }

    /**
     * Find rows by Bynder media id.
     *
     * @param string[] $mediaIds
     * @return array  rows grouped by media id (as given)
     */
    public function findByMediaIds(array $mediaIds)
    {
        $lookup = [];
        foreach ($mediaIds as $mediaId) {
            $mediaId = trim((string)$mediaId);
            if ($mediaId !== '') {
                $lookup[strtolower($mediaId)] = $mediaId;
            }
        }
        $result = [];
        foreach ($lookup as $original) {
            $result[$original] = [];
        }
        if (empty($lookup)) {
            return $result;
        }
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resource->getTableName(self::TABLE),
                    ['parent_sku', 'child_sku', 'media_id', 'image_url', 'created_at']
                )
                ->where('media_id IN (?)', array_values($lookup))
                ->order(['parent_sku ASC', 'child_sku ASC'])
        );
        foreach ($rows as $row) {
            $key = strtolower($row['media_id']);
            if (isset($lookup[$key])) {
                $result[$lookup[$key]][] = $row;
            }
        }
        return $result;
    }

    /**
     * Turn a bynder_multi_img value into table rows, keyed "child|media".
     *
     * @param string $parentSku
     * @param mixed $value
     * @return array
     */
    public function extractRows($parentSku, $value)
    {
        $data = $value;
        if (is_string($data)) {
            $data = trim($data) === '' ? [] : json_decode($data, true);
        }
        if (!is_array($data) || empty($data)) {
            return [];
        }
        // Flat list of items (or a single item) -> belongs to the parent SKU itself.
        if ($this->isItem($data)) {
            $data = [$parentSku => [$data]];
        } elseif ($this->isList($data)) {
            $data = [$parentSku => $data];
        }

        $rows = [];
        foreach ($data as $childSku => $items) {
            if (!is_array($items)) {
                continue;
            }
            if ($this->isItem($items)) {
                $items = [$items];
            }
            $childSku = trim((string)$childSku);
            if ($childSku === '') {
                $childSku = $parentSku;
            }
            foreach ($items as $item) {
                if (!is_array($item) || !isset($item['bynder_md_id'])) {
                    continue;
                }
                $mediaId = trim((string)$item['bynder_md_id']);
                if ($mediaId === '') {
                    continue;
                }
                $url = '';
                if (!empty($item['item_url']) && is_scalar($item['item_url'])) {
                    $url = (string)$item['item_url'];
                } elseif (!empty($item['thum_url']) && is_scalar($item['thum_url'])) {
                    $url = (string)$item['thum_url'];
                }
                $key = $this->rowKey($childSku, $mediaId);
                if (!isset($rows[$key])) {
                    $rows[$key] = [
                        'parent_sku' => $parentSku,
                        'child_sku' => $childSku,
                        'media_id' => $mediaId,
                        'image_url' => $url,
                    ];
                }
            }
        }
        return $rows;
    }

    /**
     * @param string $childSku
     * @param string $mediaId
     * @return string
     */
    protected function rowKey($childSku, $mediaId)
    {
        return strtolower(trim((string)$childSku)) . '|' . strtolower(trim((string)$mediaId));
    }

    /**
     * @param array $data
     * @return bool
     */
    protected function isItem(array $data)
    {
        return isset($data['bynder_md_id']) || isset($data['item_url']);
    }

    /**
     * @param array $data
     * @return bool
     */
    protected function isList(array $data)
    {
        return array_keys($data) === range(0, count($data) - 1);
    }
}
