<?php

namespace DamConsultants\Ahfproducts\Ui\DataProvider\Product;

use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderAutoReplaceDataCollectionFactory;

class AutoReplaceProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * @var $collection
     */
    protected $collection;
    /**
     * @param BynderAutoReplaceDataCollectionFactory $BynderAutoReplaceDataCollectionFactory
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        BynderAutoReplaceDataCollectionFactory $BynderAutoReplaceDataCollectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $collection = $BynderAutoReplaceDataCollectionFactory;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );
        return $this->collection = $BynderAutoReplaceDataCollectionFactory->create();
    }
}
