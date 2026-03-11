<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderSycDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderSycDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderSycData::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderSycData::class
        );
    }
}
