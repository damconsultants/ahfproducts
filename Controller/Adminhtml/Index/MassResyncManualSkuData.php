<?php

namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderConfigSyncDataCollectionFactory;
use DamConsultants\Ahfproducts\Controller\Adminhtml\Index\Psku;

class MassResyncManualSkuData extends Action
{
    /**
     * @var Filter
     */
    protected $filter;
    /**
     * @var BynderConfigSyncDataCollectionFactory
     */
    protected $collectionFactory;
    /**
     * @var \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory
     */
    protected $bynderConfigSyncDataFactory;
    protected $_productRepository;
    protected $searchCriteriaBuilder;
    protected $storeManagerInterface;
    protected $pskuController;

    public function __construct(
        Context $context,
        Filter $filter,
        BynderConfigSyncDataCollectionFactory $collectionFactory,
        \DamConsultants\Ahfproducts\Model\BynderConfigSyncDataFactory $bynderConfigSyncDataFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        Psku $pskuController
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->bynderConfigSyncDataFactory = $bynderConfigSyncDataFactory;
        $this->_productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->pskuController = $pskuController;
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $success = 0;
        $failed = 0;

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            foreach ($collection as $item) {
                try {
                    $syncModel = $this->bynderConfigSyncDataFactory->create()->load($item->getId());

                    if (!$syncModel->getId()) {
                        $failed++;
                        continue;
                    }

                    $sku = $syncModel->getSku();
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter('sku', $sku, 'eq')
                        ->create();

                    $products = $this->_productRepository->getList($searchCriteria);

                    if (count($products->getItems())) {

                        // Sync SKU
                        $this->pskuController->processSkus([$sku], "all_attribute");

                        // Delete old sync record
                        $syncModel->delete();

                        $success++;
                    } else {
                        $failed++;
                    }

                    // Reset builder filters for next iteration
                    $this->searchCriteriaBuilder = clone $this->searchCriteriaBuilder;

                } catch (\Exception $e) {
                    $failed++;
                }
            }

            if ($success) {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 SKU(s) were synced successfully.', $success)
                );
            }

            if ($failed) {
                $this->messageManager->addErrorMessage(
                    __('A total of %1 SKU(s) could not be synced.', $failed)
                );
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__($e->getMessage()));
        }

        return $resultRedirect->setPath('bynder/index/sync');
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Ahfproducts::resync');
    }
}
