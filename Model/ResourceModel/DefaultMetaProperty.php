<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class DefaultMetaProperty extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * MetaProperty
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('bynder_default_metaproperty', 'id');
    }
}
