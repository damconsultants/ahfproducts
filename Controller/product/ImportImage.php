<?php
namespace DamConsultants\Ahfproducts\Controller\Product;

class ImportImage extends \Magento\Framework\App\Action\Action
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
     * @var $logger
     */
    protected $logger;
    /**
     * Import Image.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Catalog\Model\Product $product
     * @param \Magento\Framework\Filesystem\Io\File $file
     * @param \Magento\Framework\Filesystem\Driver\File $driverFile
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Catalog\Model\Product\Action $productActionObject
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
     * @param \Magento\Framework\Registry $registry
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Catalog\Model\Product $product,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\Filesystem\Driver\File $driverFile,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Catalog\Model\Product\Action $productActionObject,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\Registry $registry,
        \Psr\Log\LoggerInterface $logger
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
        $this->logger = $logger;
        return parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('product_id');
        $sku = $this->getRequest()->getParam('sku');
        $storeId = $this->storeManagerInterface->getStore()->getId();
        $bynder_multi_img = $this->getRequest()->getParam('bynder_in');
        $bynder_image = $this->getRequest()->getParam('image');
        $old_image_array = [];
        $new_image_json = [];
        $image = $this->cookieManager->getCookie('bynder_image');
        $result = $this->resultJsonFactory->create();
        $product = $this->_product->load($id);
        $storeId = $this->storeManagerInterface->getStore()->getId();
        try {
            $dir_path = "bynderDAM_temp/";
            $img_dir = BP . '/pub/media/wysiwyg/' . $dir_path;
            
            if (!$this->file->fileExists($img_dir)) {
                $this->file->mkdir($img_dir, 0755, true);
            }
            
            if (!empty($bynder_image)) {
                $img_array =  json_decode($bynder_image, true);
            } elseif (!empty($image)) {
                $img_array =  json_decode($image, true);
            } elseif (!empty($bynder_multi_img)) {
                $img_array =  json_decode($bynder_multi_img, true);
            }
            if (count($img_array) > 0) {
                foreach ($img_array as $k => $item) {
                    if ($item['item_type'] == 'IMAGE') {
                        $item_url = trim($item['item_url']);
                        if (!empty($item_url) && isset($item["is_import"]) && $item["is_import"] == "0") {
                            $fileInfo = $this->file->getPathInfo($item_url);
                            $basename = $fileInfo['basename'];
                            $file_name = explode("?", $basename);
                            $file_name = $file_name[0];
                            $file_name = str_replace("%20", " ", $file_name);
                            $img_url = $img_dir . $file_name;
                            $roll = [];
                            if (in_array('Base', $item['image_role'])) {
                                array_push($roll, "image");
                            }
                            if (in_array('Small', $item['image_role'])) {
                                array_push($roll, "small_image");
                            }
                            if (in_array('Thumbnail', $item['image_role'])) {
                                array_push($roll, "thumbnail");
                            }
                            if (in_array('Swatch', $item['image_role'])) {
                                array_push($roll, "swatch_image");
                            }
                            $this->file->write(
                                $img_url,
                                $this->driverFile->fileGetContents($item_url)
                            );
                            $product->addImageToMediaGallery($img_url, $roll, true, false);
                            $img_label = $item["alt_text"];
                            if ($item["alt_text"] != "") {
                                $existingMediaGalleryEntries = $product->getMediaGalleryEntries();
                                foreach ($existingMediaGalleryEntries as $key => $entry) {
                                    if (empty($entry['label'])) {
                                        $entry->setLabel($img_label);
                                    }
                                }
                                $product->setStoreId(0);
                                $product->setMediaGalleryEntries($existingMediaGalleryEntries);
                            }
                            $product->save();
                            $img_array[$k]["is_import"] = "1";
                            //unlink($img_url);
                        }
                    }
                }
            }
            $array_json = json_encode($img_array, true);
            $updated_values = [
                'bynder_multi_img' => $array_json,
            ];
            $this->productActionObject->updateAttributes(
                [$id],
                $updated_values,
                $storeId
            );
            $res_data = [];
            $res_data['data']  = $img_array;
            $res_data['status'] = 1;
            $res_data['msg'] = "Image Import in Folder Successfully..!";
            $result_data = $result->setData(['status' => 1, 'message' => 'Image Import in Folder Successfully..!']);
            return $result_data;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
