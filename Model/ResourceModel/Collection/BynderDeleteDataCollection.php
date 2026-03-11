<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderDeleteDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderDeleteData::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderDeleteData::class
        );
    }
}
