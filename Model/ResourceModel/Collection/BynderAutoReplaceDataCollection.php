<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class BynderAutoReplaceDataCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * BynderConfigSyncDataCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\BynderAutoReplaceData::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\BynderAutoReplaceData::class
        );
    }
}
