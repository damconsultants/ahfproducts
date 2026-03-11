<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class Bynder extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Bynder
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('bynder_data_product', 'bynder_id');
    }
}
