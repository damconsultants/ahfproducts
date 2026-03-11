<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderMediaTable::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderMediaTable::class
        );
    }
}
