<?php

namespace DamConsultants\Ahfproducts\Ui\DataProvider\Product;

use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderDeleteDataCollectionFactory;

class DeleteProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /**
     * @var $collection
     */
    protected $collection;
    /**
     * @param BynderDeleteDataCollectionFactory $BynderDeleteDataCollectionFactory
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        BynderDeleteDataCollectionFactory $BynderDeleteDataCollectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $collection = $BynderDeleteDataCollectionFactory;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );
        return $this->collection = $BynderDeleteDataCollectionFactory->create();
    }
}
