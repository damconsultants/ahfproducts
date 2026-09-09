<?php

namespace DamConsultants\Ahfproducts\Model\Config\Source;

use Magento\Framework\App\Config\Value;

class MinutesLimit extends Value
{
    public function beforeSave()
    {
        $value = (int) $this->getValue();

        if ($value < 2) {
            $value = 2;
        }

        $this->setValue($value);

        return parent::beforeSave();
    }
}
