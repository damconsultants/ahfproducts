<?php

namespace DamConsultants\Ahfproducts\Plugin\Catalog\Product;

use DamConsultants\Ahfproducts\Model\BynderMediaSku;
use Magento\Catalog\Model\Product\Action;
use Psr\Log\LoggerInterface;

/**
 * Every Bynder sync (cron, manual SKU sync, re-sync, compact view) saves images through
 * Product\Action::updateAttributes(['bynder_multi_img' => ...]). After that write we
 * refresh the bynder_media_sku table for the same products.
 */
class BynderMediaSkuActionPlugin
{
    /**
     * @var BynderMediaSku
     */
    protected $bynderMediaSku;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param BynderMediaSku $bynderMediaSku
     * @param LoggerInterface $logger
     */
    public function __construct(BynderMediaSku $bynderMediaSku, LoggerInterface $logger)
    {
        $this->bynderMediaSku = $bynderMediaSku;
        $this->logger = $logger;
    }

    /**
     * @param Action $subject
     * @param mixed $result
     * @param array $productIds
     * @param array $attrData
     * @param int $storeId
     * @return mixed
     */
    public function afterUpdateAttributes(Action $subject, $result, $productIds, $attrData, $storeId)
    {
        if (is_array($attrData) && array_key_exists(BynderMediaSku::ATTRIBUTE, $attrData)) {
            try {
                $this->bynderMediaSku->syncProductIds(
                    (array)$productIds,
                    $attrData[BynderMediaSku::ATTRIBUTE]
                );
            } catch (\Exception $e) {
                // Never break the product sync because of the mapping table.
                $this->logger->error('Bynder media sku table sync failed: ' . $e->getMessage());
            }
        }
        return $result;
    }
}
