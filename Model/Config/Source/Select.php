<?php

namespace DamConsultants\Ahfproducts\Model\Config\Source;

class Select implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * To option array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'image', 'label' => __('Image')],
            ['value' => 'video', 'label' => __('Video')],
            ['value' => 'document', 'label' => __('Document')],
			['value' => 'all_attribute', 'label' => __('All Attributes')]
          ];
    }
}
