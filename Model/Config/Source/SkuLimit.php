<?php

namespace DamConsultants\Ahfproducts\Model\Config\Source;

use Magento\Framework\App\Config\Value;

class SkuLimit extends Value
{
    public function beforeSave()
    {
        $value = (int) $this->getValue();

        if ($value < 0) {
            $value = 1;
        }

        $this->setValue($value);

        return parent::beforeSave();
    }
}
