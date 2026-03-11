<?php

namespace DamConsultants\Ahfproducts\Model;

class MetaProperty extends \Magento\Framework\Model\AbstractModel
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
     * Meta Property
     *
     * @return $this
     */
    protected function _construct()
    {
        $this->_init(\DamConsultants\Ahfproducts\Model\ResourceModel\MetaProperty::class);
    }
}
