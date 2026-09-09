<?php

namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class UpdateSkuReport extends Action
{
    public const ADMIN_RESOURCE = 'DamConsultants_Ahfproducts::menu_item10';

    private $resultPageFactory;

    public function __construct(Action\Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Update SKU Reports'));
        return $page;
    }
}
