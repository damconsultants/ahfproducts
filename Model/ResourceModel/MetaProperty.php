<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class MetaProperty extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * MetaProperty
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('bynder_metaproperty', 'id');
    }
}
