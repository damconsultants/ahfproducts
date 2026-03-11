<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class MetaPropertyCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * MetaPropertyCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\MetaProperty::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\MetaProperty::class
        );
    }
}
