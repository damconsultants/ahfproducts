<?php

namespace DamConsultants\Ahfproducts\Model\ResourceModel;

class ApiBynderMediaTable extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Bynder Syc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init('api_response_media_data', 'id');
    }
}
