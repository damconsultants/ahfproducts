<?php

namespace DamConsultants\Ahfproducts\Observer;

use DamConsultants\Ahfproducts\Model\BynderMediaSku;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Removes bynder_media_sku rows when a product is deleted.
 */
class BynderMediaSkuProductDelete implements ObserverInterface
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
            $this->bynderMediaSku->deleteParentSku($product->getSku());
        } catch (\Exception $e) {
            $this->logger->error('Bynder media sku table delete failed: ' . $e->getMessage());
        }
    }
}
