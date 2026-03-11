<?php

namespace DamConsultants\Ahfproducts\Model;

class BynderSycData extends \Magento\Framework\Model\AbstractModel
{
    protected const CACHE_TAG = 'DamConsultants_Ahfproducts';

    /**
     * @var $_cacheTag
     */
    protected $_cacheTag = 'DamConsultants_Ahfproducts';

    /**
     * @var $_eventPrefix
     */
    protected $_eventPrefix = 'DamConsultants_Ahfproducts';

    /**
     * Ahfproducts Syc Data
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(\DamConsultants\Ahfproducts\Model\ResourceModel\BynderSycData::class);
    }
}
