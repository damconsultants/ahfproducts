<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderUpdateSkuTokenCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderUpdateSkuToken::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderUpdateSkuToken::class
        );
    }
}
