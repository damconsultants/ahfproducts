<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderTempDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderTempData::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderTempData::class
        );
    }
}
