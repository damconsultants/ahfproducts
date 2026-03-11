<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class MagentoSku extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Bynder Syc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('bynder_update_sku', 'id');
    }
}
