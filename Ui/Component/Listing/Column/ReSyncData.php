<?php
namespace DamConsultants\Ahfproducts\Ui\Component\Listing\Column;

use Exception;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderSycDataCollectionFactory;

class ReSyncData extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var $urlBuilder
     */
    protected $urlBuilder;
    /**
     * @var $_resource
     */
    protected $_resource;
    /**
     * @var $bynderSycDataFactory
     */
    protected $bynderSycDataFactory;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
     /**
     * @var bynderSycDataCollectionFactory
     */
    protected $bynderSycDataCollectionFactory;
    /**
     * Closed constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $BynderSycDataFactory
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param BynderSycDataCollectionFactory $bynderSycDataCollectionFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \DamConsultants\Ahfproducts\Model\BynderSycDataFactory $BynderSycDataFactory,
        BynderSycDataCollectionFactory $bynderSycDataCollectionFactory,
        \Magento\Framework\App\ResourceConnection $resource,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->_resource = $resource;
        $this->bynderSycDataFactory = $BynderSycDataFactory;
        $this->_productRepository = $productRepository;
        $this->bynderSycDataCollectionFactory = $bynderSycDataCollectionFactory;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }
    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $sku = $item["sku"];
                try {
                    $_product = $this->_productRepository->get($sku);
                    $product_bynder_cron_val = $_product->getBynderCronSync();
                    if (isset($item['id'])) {
                        $viewUrlPath = $this->getData('config/viewUrlPath');
                        $urlEntityParamName = $this->getData('config/urlEntityParamName');
                        if ($item['media_id'] == null && $product_bynder_cron_val != null) {
                            $item[$this->getData('name')] = [
                                'view' => [
                                    'href' => $this->urlBuilder->getUrl(
                                        $viewUrlPath,
                                        [
                                            $urlEntityParamName => $item['id'],
                                        ]
                                    ),
                                    'label' => __('Re-Sync'),
                                    'class' => 'action-primary',
                                ],
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $collection = $this->bynderSycDataCollectionFactory->create()
                    ->addFieldToFilter('sku', ['eq' => $sku])->load();
                    foreach ($collection as $itemToDelete) {
                        $this->bynderSycDataFactory->create()->load($itemToDelete->getId())->delete();
                    }
                }
            }
        }
        return $dataSource;
    }
}
