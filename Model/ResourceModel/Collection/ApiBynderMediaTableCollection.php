<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class ApiBynderMediaTableCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\ApiBynderMediaTable::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\ApiBynderMediaTable::class
        );
    }
}
