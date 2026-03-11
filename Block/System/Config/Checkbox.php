<?php

namespace DamConsultants\Ahfproducts\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;

class Checkbox extends Field
{
    /**
     * Block template.
     *
     * @var string
     */
    protected $_template = 'DamConsultants_Ahfproducts::system/config/checkbox.phtml';

    /**
     * Retrieve element HTML markup.
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $this->setNamePrefix($element->getName())
            ->setHtmlId($element->getHtmlId());
        return $this->_toHtml();
    }
    
    /**
     * Getvalue.
     *
     * @return $this
     */
    public function getValues()
    {
        $values = [];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $val = $objectManager->create(\DamConsultants\Ahfproducts\Model\Config\Source\Checkbox::class);
        $valuess = $val->toOptionArray();
        foreach ($valuess as $value) {
            $values[$value['value']] = $value['label'];
        }
        return $values;
    }

    /**
     * GetNPrefix.
     *
     * @return $this
     */
    public function getNPrefix()
    {
        return $this->getNamePrefix();
    }

    /**
     * GetId.
     *
     * @return $this
     */
    public function getId()
    {
        return $this->getHtmlId();
    }

    /**
     * GetCheck.
     *
     * @return $this
     * @param string $name
     */
    public function getCheck($name)
    {
        return $this->getIsChecked($name);
    }
}
