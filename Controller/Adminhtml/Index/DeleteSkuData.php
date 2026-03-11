<?php
namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class DeleteSkuData extends Action
{
    /**
     * @var $bynderSycDataFactory
     */
    public $magentoSku;
    /**
     * Closed constructor.
     *
     * @param Context $context
     * @param DamConsultants\Ahfproducts\Model\MagentoSkuFactory $MagentoSkuFactory
     */
    public function __construct(
        Context $context,
        \DamConsultants\Ahfproducts\Model\MagentoSkuFactory $MagentoSkuFactory
    ) {
        $this->magentoSku = $MagentoSkuFactory;
        parent::__construct($context);
    }
    /**
     * Execute
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');
        try {
            $syncModel = $this->magentoSku->create();
            $syncModel->load($id);
            $syncModel->delete();
            $this->messageManager->addSuccessMessage(__('You deleted the sync data.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $resultRedirect->setPath('bynder/index/sku');
    }
}
