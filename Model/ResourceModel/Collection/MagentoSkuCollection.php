<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel\Collection;

class MagentoSkuCollection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    
    /**
     * MagentoSkuCollection
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(
            \DamConsultants\Ahfproducts\Model\MagentoSku::class,
            \DamConsultants\Ahfproducts\Model\ResourceModel\MagentoSku::class
        );
    }
}
