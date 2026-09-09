<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class BynderUpdateSkuReport extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('bynder_update_sku_report', 'id');
    }
}
