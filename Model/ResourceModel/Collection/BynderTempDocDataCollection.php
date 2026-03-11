<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderTempDocDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderTempDocData::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderTempDocData::class
        );
    }
}
