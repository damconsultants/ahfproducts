<?php
namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use DamConsultants\Ahfproducts\Controller\Adminhtml\Index\Psku;

class ReSyncDataUpdate extends Action
{
    /**
     * @var $BynderConfigSyncDataFactory
     */
    public $bynderSycDataFactory;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $action
     */
    protected $action;
    /**
     * @var $searchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var Psku
     */
    protected $pskuController;
    /**
     * Closed constructor.
     *
     * @param Context $context
     * @param \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory $BynderConfigSyncDataFactory
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param Psku $pskuController
     */
    public function __construct(
        Context $context,
        \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory $BynderConfigSyncDataFactory,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        Psku $pskuController
    ) {
        $this->bynderSycDataFactory = $BynderConfigSyncDataFactory;
        $this->_productRepository = $productRepository;
        $this->action = $action;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->pskuController = $pskuController;
        parent::__construct($context);
    }
    /**
     * Execute
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = $this->getRequest()->getParam('id');
        $storeId = $this->storeManagerInterface->getStore()->getId();
        try {
            $syncModel = $this->bynderSycDataFactory->create();
            $syncModel->load($id);
            $sku = $syncModel->getSku();
            $searchCriteria = $this->searchCriteriaBuilder->addFilter("sku", $sku, 'eq')->create();
            $products = $this->_productRepository->getList($searchCriteria);
            $Items = $products->getItems();
            if (count($Items) != 0) {
                $this->pskuController->processSkus([$sku], "all_attribute");
                $syncModel->delete();
                $this->messageManager->addSuccessMessage(__('SKU ('. $sku.') sync successfully.'));
            } else {
                $this->messageManager->addSuccessMessage(__('This SKU ('. $sku.') not available in Products List.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('This SKU ('. $sku.') not available in Products List.'));
        }
        return $resultRedirect->setPath('bynder/index/sync');
    }
    /**
     * Is Allowed
     */
    public function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Ahfproducts::resync');
    }
}
