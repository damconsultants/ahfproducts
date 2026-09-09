<?php

namespace DamConsultants\Ahfproducts\Ui\DataProvider;

use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderUpdateSkuTokenCollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class UpdateSkuReportDataProvider extends AbstractDataProvider
{
    public function __construct(
        BynderUpdateSkuTokenCollectionFactory $collectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
}
