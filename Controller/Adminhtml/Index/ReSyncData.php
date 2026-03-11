<?php
namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class ReSyncData extends Action
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
     * Closed constructor.
     *
     * @param Context $context
     * @param \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $BynderSycDataFactory
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        Context $context,
        \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $BynderSycDataFactory,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
    ) {
        $this->bynderSycDataFactory = $BynderSycDataFactory;
        $this->_productRepository = $productRepository;
        $this->action = $action;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManagerInterface = $storeManagerInterface;
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
            $updated_values = [
                'bynder_cron_sync' => null
            ];
            $searchCriteria = $this->searchCriteriaBuilder->addFilter("sku", $sku, 'eq')->create();
            $products = $this->_productRepository->getList($searchCriteria);
            $Items = $products->getItems();
            if (count($Items) != 0) {
                $_product = $this->_productRepository->get($sku);
                $product_ids = $_product->getId();
                $this->action->updateAttributes(
                    [$product_ids],
                    $updated_values,
                    $storeId
                );
                $syncModel->setLable('2');
                $syncModel->save();
                $this->messageManager->addSuccessMessage(__('SKU ('. $sku.') will re-sync again.'));
            } else {
                $this->messageManager->addSuccessMessage(__('This SKU ('. $sku.') not available in Products List.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('This SKU ('. $sku.') not available in Products List.'));
        }
        return $resultRedirect->setPath('bynder/index/grid');
    }
    /**
     * Is Allowed
     */
    public function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Ahfproducts::resync');
    }
}
