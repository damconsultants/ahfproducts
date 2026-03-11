<?php
namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use DamConsultants\Ahfproducts\Model\BynderTempDataFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderTempDataCollectionFactory;
use DamConsultants\Ahfproducts\Model\BynderTempDocDataFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderTempDocDataCollectionFactory;

class AddDatas extends \Magento\Backend\App\Action
{
    /**
     * @var $_pageFactory
     */
    protected $_pageFactory;
    /**
     * @var $_product
     */
    protected $_product;
    /**
     * @var $file
     */
    protected $file;
    /**
     * @var $resultJsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var $driverFile
     */
    protected $driverFile;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $cookieManager
     */
    protected $cookieManager;
    /**
     * @var $productActionObject
     */
    protected $productActionObject;
    /**
     * @var $_registry
     */
    protected $_registry;
    /**
     * @var $_resource
     */
    protected $_resource;
    /**
     * @var $cookieMetadataFactory
     */
    protected $cookieMetadataFactory;
    /**
     * @var $bynderTempData
     */
    protected $bynderTempData;
    /**
     * @var $bynderTempDataCollectionFactory
     */
    protected $bynderTempDataCollectionFactory;
    /**
     * @var $bynderTempDocData
     */
    protected $bynderTempDocData;
    /**
     * @var $bynderTempDocDataCollectionFactory
     */
    protected $bynderTempDocDataCollectionFactory;

    /**
     * Add Data.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Framework\Filesystem\Io\File $file
     * @param \Magento\Framework\Filesystem\Driver\File $driverFile
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Catalog\Model\Product\Action $productActionObject
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param BynderTempDataFactory $bynderTempData
     * @param BynderTempDataCollectionFactory $bynderTempDataCollectionFactory
     * @param BynderTempDocDataFactory $bynderTempDocData
     * @param BynderTempDocDataCollectionFactory $bynderTempDocDataCollectionFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Catalog\Model\Product $product,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\Filesystem\Driver\File $driverFile,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Catalog\Model\Product\Action $productActionObject,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        BynderTempDataFactory $bynderTempData,
        BynderTempDataCollectionFactory $bynderTempDataCollectionFactory,
        BynderTempDocDataFactory $bynderTempDocData,
        BynderTempDocDataCollectionFactory $bynderTempDocDataCollectionFactory
    ) {
        $this->_pageFactory = $pageFactory;
        $this->_product = $product;
        $this->file = $file;
        $this->resultJsonFactory = $jsonFactory;
        $this->driverFile = $driverFile;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->cookieManager = $cookieManager;
        $this->productActionObject = $productActionObject;
        $this->_registry = $registry;
        $this->_resource = $resource;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->bynderTempData = $bynderTempData;
        $this->bynderTempDataCollectionFactory = $bynderTempDataCollectionFactory;
        $this->bynderTempDocData = $bynderTempDocData;
        $this->bynderTempDocDataCollectionFactory = $bynderTempDocDataCollectionFactory;
        return parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $product_id = $this->getRequest()->getParam('product_id');
        $coockie_id = $this->getRequest()->getParam('image_coockie_id');
        $bynder_image = $this->getRequest()->getParam('image');
        if ($coockie_id == 0) {
            $data = [
                "value" => $bynder_image,
                "product_id" => $product_id
            ];
            $bynderTempData = $this->bynderTempData->create();
            $bynderTempData->setData($data);
            $bynderTempData->save();
            $collectionData = $this->bynderTempDataCollectionFactory->create()->load();
            if (!empty($collectionData)) {
                $lastAddedId = "";
                foreach ($collectionData as $data) {
                    $lastAddedId = $data['id'];
                }
            }
        } else {
            $records = $this->bynderTempDataCollectionFactory->create();
            $records->addFieldToFilter('product_id', ['eq' => [$product_id]])->load();
            if (empty($records)) {
                $data = [
                    "value" => $bynder_image,
                    "product_id" => $product_id
                ];
                $bynderTempData = $this->bynderTempData->create();
                $bynderTempData->setData($data);
                $bynderTempData->save();
                $collectionData = $this->bynderTempDataCollectionFactory->create()->load();
                if (!empty($collectionData)) {
                    $lastAddedId = "";
                    foreach ($collectionData as $data) {
                        $lastAddedId = $data['id'];
                    }
                }
            } else {
                $new_data = [
                    "value" => $bynder_image,
                    "product_id" => $product_id
                ];
                $bynderTempData = $this->bynderTempData->create();
                $bynderTempData->load($coockie_id);
                $bynderTempData->setData($new_data);
                $bynderTempData->save();
                $lastAddedId = $coockie_id;
            }
        }
        $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
        $publicCookieMetadata->setDurationOneYear();
        $publicCookieMetadata->setPath('/');
        $publicCookieMetadata->setHttpOnly(false);
        $this->cookieManager->setPublicCookie(
            'image_coockie_id',
            $lastAddedId,
            $publicCookieMetadata
        );
    }
}
