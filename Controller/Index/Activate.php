<?php

namespace DamConsultants\Ahfproducts\Controller\Index;

class Activate extends \Magento\Framework\App\Action\Action
{
    /**
     * @var $_helperData
     */
    protected $_helperData;
    /**
     * Activate
     * @param \Magento\Framework\App\Action\Context $context
     * @param \DamConsultants\Ahfproducts\Helper\Data $helperData
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \DamConsultants\Ahfproducts\Helper\Data $helperData
    ) {

        $this->_helperData = $helperData;
        return parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $getlicenceKey = $this->_helperData->getLicenceKey();
        return $this->getResponse()->setBody($getlicenceKey);
    }
    /**
     * Is Allowed
     *
     * @return $this
     */
    protected function _isAllowed()
    {
        return true;
    }
}
