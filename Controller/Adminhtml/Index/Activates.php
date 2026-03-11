<?php

namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;

class Activates extends Action
{
    /**
     * @var \DamConsultants\Ahfproducts\Helper\Data
     */
    protected $_helperData;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * Constructor
     *
     * @param Action\Context $context
     * @param \DamConsultants\Ahfproducts\Helper\Data $helperData
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \DamConsultants\Ahfproducts\Helper\Data $helperData,
        JsonFactory $jsonFactory
    ) {
        $this->_helperData = $helperData;
        $this->jsonFactory = $jsonFactory;
        parent::__construct($context);
    }

    /**
     * Execute method to handle AJAX request
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $getlicenceKey = $this->_helperData->getLicenceKey();
        return $this->getResponse()->setBody($getlicenceKey);
    }

    /**
     * Check if the admin user has permissions to access this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Ahfproducts::activates'); // Adjust permission if needed
    }
}
