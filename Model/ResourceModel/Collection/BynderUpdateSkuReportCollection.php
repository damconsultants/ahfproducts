<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderUpdateSkuReportCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderUpdateSkuReport::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderUpdateSkuReport::class
        );
    }
}
