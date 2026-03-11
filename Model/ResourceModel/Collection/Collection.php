<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Collection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\Bynder::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\Bynder::class
        );
    }
}
