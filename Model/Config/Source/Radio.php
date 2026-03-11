<?php

namespace DamConsultants\Ahfproducts\Model\Config\Source;

class Radio implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var $bulk
     */
    protected $bulk;
    /**
     * @var $_options
     */
    protected $_options;
    /**
     * Radio
     * @param \Magento\ConfigurableProduct\Block\Adminhtml\Product\Steps\Bulk $bulk
     */
    public function __construct(
        \Magento\ConfigurableProduct\Block\Adminhtml\Product\Steps\Bulk $bulk
    ) {
        $this->bulk = $bulk;
    }
    /**
     * To option array
     *
     * @return $this
     */
    public function toOptionArray()
    {
        $collection = $this->bulk->getMediaAttributes();

        $this->_options = [];

        foreach ($collection as $attribute) {
                $this->_options[] = [
                    'label' => __($attribute->getFrontendLabel()),
                    'value' => $attribute->getFrontendLabel()
                ];
        }
        return $this->_options;
    }
}
