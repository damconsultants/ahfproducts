<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class BynderMediaTable extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Bynder Syc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('media_data', 'id');
    }
}
