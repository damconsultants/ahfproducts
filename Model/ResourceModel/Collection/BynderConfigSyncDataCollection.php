<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderConfigSyncDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderConfigSyncData::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderConfigSyncData::class
        );
    }
}
