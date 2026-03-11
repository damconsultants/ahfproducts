<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class DefaultMetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\DefaultMetaProperty::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\DefaultMetaProperty::class
        );
    }
}
