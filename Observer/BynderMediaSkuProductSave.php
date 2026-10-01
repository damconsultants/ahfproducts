<?php

namespace DamConsultants\Ahfproducts\Observer;

use DamConsultants\Ahfproducts\Model\BynderMediaSku;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps bynder_media_sku in step when bynder_multi_img (or the SKU) changes through a
 * normal product save (e.g. the delete cron or the admin product form).
 */
class BynderMediaSkuProductSave implements ObserverInterface
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
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getSku()) {
            return;
        }
        try {
            $skuChanged = $product->getOrigData('sku') !== null
                && $product->getOrigData('sku') !== $product->getSku();
            if ($skuChanged) {
                $this->bynderMediaSku->deleteParentSku($product->getOrigData('sku'));
            }
            if ($skuChanged
                || ($product->hasData(BynderMediaSku::ATTRIBUTE)
                    && $product->dataHasChangedFor(BynderMediaSku::ATTRIBUTE))
            ) {
                $this->bynderMediaSku->syncParentSku(
                    $product->getSku(),
                    $product->getData(BynderMediaSku::ATTRIBUTE)
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Bynder media sku table sync failed: ' . $e->getMessage());
        }
    }
}
