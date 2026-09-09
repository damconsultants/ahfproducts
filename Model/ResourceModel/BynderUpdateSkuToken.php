<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class BynderUpdateSkuToken extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('bynder_update_sku_token', 'id');
    }
}
