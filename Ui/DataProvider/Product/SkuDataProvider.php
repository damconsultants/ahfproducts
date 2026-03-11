<?php

namespace DamConsultants\Ahfproducts\Ui\DataProvider\Product;

use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MagentoSkuCollectionFactory;

class SkuDataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * @var $collection
     */
    protected $collection;
    /**
     * @param MagentoSkuCollectionFactory $MagentoSkuCollectionFactory
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        MagentoSkuCollectionFactory $MagentoSkuCollectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $collection = $MagentoSkuCollectionFactory;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );
        return $this->collection = $MagentoSkuCollectionFactory->create();
    }
}
