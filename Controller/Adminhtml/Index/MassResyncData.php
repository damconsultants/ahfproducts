<?php
namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderSycDataCollectionFactory;

class MassResyncData extends Action
{
    /**
     * @var $collectionFactory
     */
    public $collectionFactory;
    /**
     * @var $filter
     */
    public $filter;
    /**
     * @var $bynderFactory
     */
    protected $bynderFactory;
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
     * @param Filter $filter
     * @param BynderSycDataCollectionFactory $collectionFactory
     * @param \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $bynderFactory
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \Magento\Catalog\Model\Product\Action $action
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     */
    public function __construct(
        Context $context,
        Filter $filter,
        BynderSycDataCollectionFactory $collectionFactory,
        \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $bynderFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\Product\Action $action,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->bynderFactory = $bynderFactory;
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
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $count = 0;
            $not_exist_skus = "";
            $product_ids = [];
            foreach ($collection as $model) {
                $searchCriteria = $this->searchCriteriaBuilder->addFilter("sku", $model->getSku(), 'eq')->create();
                $products = $this->_productRepository->getList($searchCriteria);
                $items = $products->getItems();
                if (count($items) != 0) {
                    if ($model->getLable() == 0) {
                        $_product = $this->_productRepository->get($model->getSku());
                        $product_ids[] = $_product->getId();
                        $model = $this->bynderFactory->create()->load($model->getId());
                        $model->setLable('2');
                        $model->save();
                        $count++;
                    }
                } else {
                    if ($not_exist_skus == "") {
                        $not_exist_skus = $model->getSku();
                    } else {
                        $not_exist_skus .= ",".$model->getSku();
                    }
                }
            }
            if ($not_exist_skus != "") {
                $this->messageManager->addSuccessMessage(__('This SKU ('. $not_exist_skus .') not available in Products list.'));
            }
            $updated_values = [
                'bynder_cron_sync' => null
            ];
            if (count($product_ids) > 0) {
                $this->action->updateAttributes(
                    $product_ids,
                    $updated_values,
                    $storeId
                );
                $this->messageManager->addSuccess(__('A total of %1 data(s) have been Re-Sync.', $count));
            }
        } catch (\Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('bynder/index/grid');
    }
    /**
     * Is Allowed
     */
    public function _isAllowed()
    {
        return $this->_authorization->isAllowed('DamConsultants_Ahfproducts::resync');
    }
}
