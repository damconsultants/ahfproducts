<?php

namespace DamConsultants\Ahfproducts\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Bounteous\SkuAlias\Model\ResourceModel\Alias\CollectionFactory as AliasCollectionFactory;
use Bounteous\SkuAlias\Api\AliasRepositoryInterface;

class Data extends AbstractHelper
{
    /**
     * @var $storeScope
     */
    protected $storeScope;
    /**
     * @var $productrepository
     */
    protected $productrepository;
    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     * @var $_curl
     */
    protected $_curl;
    /**
     * @var $by_redirecturl
     */
    public $by_redirecturl;
    /**
     * @var $bynderDomain
     */
    public $bynderDomain = "";
    /**
     * @var $cookieMetadataFactory
     */
    protected $cookieMetadataFactory;
    /**
     * @var $cookieManager
     */
    protected $cookieManager;
    /**
     * @var $_storeManager
     */
    protected $_storeManager;
    /**
     * @var $_bulk
     */
    protected $_bulk;
    /**
     * @var \Magento\Framework\Registry
     * @deprecated Use product repository instead
     */
    protected $_registry;
    /**
     * @var $permanent_token
     */
    public $permanent_token = "";
    private AliasCollectionFactory $aliasCollectionFactory;
    private AliasRepositoryInterface $aliasRepository;

    public const BYNDER_DOMAIN = 'bynderconfig/bynder_credential/bynderdomain';
    public const PERMANENT_TOKEN = 'bynderconfig/bynder_credential/permanent_token';
    public const LICENCE_TOKEN = 'bynderconfig/bynder_credential/licenses_key';
    public const RADIO_BUTTON = 'byndeimageconfig/bynder_image/selectimage';
    public const FETCH_CRON = 'cronimageconfig/configurable_cron/fetch_enable';
    public const AUTO_CRON = 'cronimageconfig/auto_add_bynder/auto_enable';
    public const DELETE_CRON = 'cronimageconfig/delete_cron_bynder/delete_enable';
    public const FETCH_PRODUCT_SKU_LIMIT = 'cronimageconfig/configurable_cron/fetch_product_sku_limt';
    public const AUTO_PRODUCT_SKU_LIMIT = 'cronimageconfig/auto_add_bynder/auto_product_sku_limt';
    public const PRODUCT_SKU_LIMIT = 'cronimageconfig/set_limit_product_sku/product_sku_limt';
    public const API_CALLED = 'https://developer.thedamconsultants.com/';
    public const IFRAME_URL = 'https://trello.thedamconsultants.com/bynder-registration';

    /**
     * Data Helper
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productrepository
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\ConfigurableProduct\Block\Adminhtml\Product\Steps\Bulk $bulk
     * @param AliasCollectionFactory $aliasCollectionFactory
     * @param AliasRepositoryInterface $aliasRepository
     */
    public function __construct(
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Catalog\Api\ProductRepositoryInterface $productrepository,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $registry,
        \Magento\ConfigurableProduct\Block\Adminhtml\Product\Steps\Bulk $bulk,
        AliasCollectionFactory $aliasCollectionFactory,
        AliasRepositoryInterface $aliasRepository
    ) {
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->productrepository = $productrepository;
        $this->filesystem = $filesystem;
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_storeManager = $storeManager;
        $this->_curl = $curl;
        $this->_bulk = $bulk;
        $this->_registry = $registry;
        $this->aliasCollectionFactory = $aliasCollectionFactory;
        $this->aliasRepository = $aliasRepository;
        parent::__construct($context);
    }
    /**
     * Get Bulk Image Roll
     *
     * @return $this
     */
    public function getBulkImageRoll()
    {
        return $this->_bulk->getMediaAttributes();
    }
    /**
     * Get Image Roll
     *
     * @return $this
     * @param string $currentProduct
     */
    public function getProduct($currentProduct)
    {
        return $this->_registry->registry($currentProduct);
    }
    /**
     * Get Product Id
     *
     * @return $this
     * @param string $productId
     */
    public function getProductById($productId)
    {

        return $this->productrepository->getById($productId);
    }
    
    /**
     * Get Store Config
     *
     * @return $this
     * @param string $storePath
     * @param string $storeId
     */
    public function getStoreConfig($storePath, $storeId = null)
    {
        return $this->_scopeConfig->getValue($storePath, ScopeInterface::SCOPE_STORE, $storeId);
    }
    /**
     * Get Bynder Domain
     *
     * @return $this
     */
    public function getBynderDomain()
    {
        return (string) $this->getStoreConfig(self::BYNDER_DOMAIN);
    }
    /**
     * Get Permanent Token
     *
     * @return $this
     */
    public function getPermanentToken()
    {
        return (string) $this->getStoreConfig(self::PERMANENT_TOKEN);
    }
    /**
     * Get Permanent Token
     *
     * @param string $path
     * @return $this
     */
    public function getDeleteCron($path)
    {
        return (string) $this->getStoreConfig($path);
    }
    /**
     * Get Licence Token
     *
     * @return $this
     */
    public function getLicenceToken()
    {
        return (string) $this->getStoreConfig(self::LICENCE_TOKEN);
    }
    /**
     * Bynde Image Config
     *
     * @return $this
     */
    public function byndeimageconfig()
    {
        return (bool) $this->getStoreConfig(self::RADIO_BUTTON);
    }
    /**
     * Get Product Sku Limit Config
     *
     * @return $this
     */
    /*public function getProductSkuLimitConfig()
    {
        return (string) $this->getStoreConfig(self::PRODUCT_SKU_LIMIT);
    }*/
    /**
     * Get Product Sku Limit Config
     *
     * @return $this
     */
    public function getAutoProductSkuLimitConfig()
    {
        return (string) $this->getStoreConfig(self::AUTO_PRODUCT_SKU_LIMIT);
    }
    /**
     * Get Product Sku Limit Config
     *
     * @return $this
     */
    public function getFetchProductSkuLimitConfig()
    {
        return (string) $this->getStoreConfig(self::FETCH_PRODUCT_SKU_LIMIT);
    }
    /**
     * Get Bynder Dom
     *
     * @return $this
     */
    public function getBynderDom()
    {
        return (string) $this->getStoreConfig(self::BYNDER_DOMAIN);
    }
    /**
     * Get Iframe Url
     *
     * @return $this
     */
    public function getIframeUrl()
    {
        return self::IFRAME_URL;
    }
    /**
     * Get Fetch cron enable
     *
     * @return $this
     */
    public function getFetchCronEnable()
    {
        return $this->getStoreConfig(self::FETCH_CRON);
    }
    /**
     * Get Auto cron enable
     *
     * @return $this
     */
    public function getAutoCronEnable()
    {
        return $this->getStoreConfig(self::AUTO_CRON);
    }
    /**
     * Get Auto cron enable
     *
     * @return $this
     */
    public function getDeleteCronEnable()
    {
        return $this->getStoreConfig(self::DELETE_CRON);
    }
    /**
     * Get Permanen Token
     *
     * @return $this
     */
    public function getPermanenToken()
    {
        return (string) $this->getStoreConfig(self::PERMANENT_TOKEN);
    }
    /**
     * Get Load Credential
     *
     * @return $this
     */
    public function getLoadCredential()
    {

        $this->bynderDomain = $this->getBynderDom();
        $this->permanent_token = $this->getPermanenToken();
        $this->by_redirecturl = $this->getRedirecturl();
        if (!empty($this->bynderDomain) && !empty($this->permanent_token) && !empty($this->by_redirecturl)) {
            return 1;
        } else {
            return "Bynder authentication failed | Please check your credential";
        }
    }
    /**
     * Get Redirecturl
     *
     * @return $this
     */
    public function getRedirecturl()
    {
        return (string) $this->getbaseurl() . "bynder/redirecturl";
    }

    /**
     * Get baseurl
     *
     * @return $this
     */
    public function getbaseurl()
    {
        $url = $this->_storeManager->getStore()->getBaseUrl();
        return $url;
    }
    /**
     * Get Config
     *
     * @return $this
     * @param string $path
     */
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
    /**
     * Get CheckBynder
     *
     * @return $this
     */
    public function getCheckBynder()
    {
        $fields = [
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'licence_token' => $this->getLicenceToken()
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-check-bynder-license');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-check-bynder-license', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get DerivativesImage
     *
     * @return $this
     * @param array $bynder_auth
     */
    public function getDerivativesImage($bynder_auth)
    {

        $fields = [
            'bynder_domain' => $bynder_auth['bynderDomain'],
            'redirectUri' => $bynder_auth['redirectUri'],
            'permanent_token' => $bynder_auth['token'],
            'databaseId' => $bynder_auth['og_media_ids'],
            'daatasetType' => $bynder_auth['dataset_types'],
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'licence_token' => $this->getLicenceToken(),
            'bynder_metaproperty_collection' => $bynder_auth['collection_data_value']
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-magento-derivatives');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-magento-derivatives', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get LicenceKey
     *
     * @return $this
     */
    public function getLicenceKey()
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl()
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-get-license-key');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-get-license-key', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderChangemetadataAssets
     *
     * @return $this
     * @param string $product_url
     * @param string $url_data
     */
    public function getBynderChangemetadataAssets($product_url, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-change-metadata-magento');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-change-metadata-magento', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderChangemetadataAssetsDoc
     *
     * @return $this
     * @param string $product_url
     * @param string $url_data
     */
    public function getBynderChangemetadataAssetsDoc($product_url, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-change-metadata-magento-doc');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-change-metadata-magento-doc', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderChangemetadataAssetsVideo
     *
     * @return $this
     * @param string $product_url
     * @param string $url_data
     */
    public function getBynderChangemetadataAssetsVideo($product_url, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'product_url' => $product_url,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-change-metadata-magento-video');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-change-metadata-magento-video', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderDataCmsPage
     *
     * @return $this
     * @param string $CMSPageURL
     * @param string $url_data
     */
    public function getBynderDataCmsPage($CMSPageURL, $url_data)
    {

        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'cmspage_url' => $CMSPageURL,
            'bynder_multi_img' => $url_data
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-change-metadata-magento-cms-page');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-change-metadata-magento-cms-page', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get BynderMetaProperites
     *
     * @return $this
     */
    public function getBynderMetaProperites()
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken()
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);
        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-get-bynder-meta-properites');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-get-bynder-meta-properites', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get ImageSyncWithProperties
     *
     * @return $this
     * @param string $sku_id
     * @param string $property_id
     * @param string $collection_data_value
     */
    public function getImageSyncWithProperties($sku_id, $property_id, $collection_data_value)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'property_id' => $property_id,
            'bynder_metaproperty_collection' => $collection_data_value
        ];

        $jsonData = '{}';
        $fields = json_encode($fields);
        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-bynder-skudetails-new');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-bynder-skudetails-new', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get DataRemoveForMagento
     *
     * @return $this
     * @param string $sku_id
     * @param string $media_Id
     * @param string $metaProperty_id
     */
    public function getDataRemoveForMagento($sku_id, $media_Id, $metaProperty_id)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'media_id' => $media_Id,
            'property_id' => $metaProperty_id
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-sku-data-remove-for-magento');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-sku-data-remove-for-magento', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Get DataRemoveForMagento
     *
     * @return $this
     * @param string $sku_id
     * @param string $media_Id
     * @param string $metaProperty_id
     */
    public function getAddedCompactviewSkuFromBynder($sku_id, $media_Id, $metaProperty_id)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $sku_id,
            'media_id' => $media_Id,
            'property_id' => $metaProperty_id
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-added-compactview-sku-from-bynder');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-added-compactview-sku-from-bynder', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }

    /**
     * Get DataRemoveForMagento
     *
     * @return $this
     * @param string $product_sku_key
     * @param string $metaProperty_Collections
     * @param string $image
     */
    public function getUpdateBynderImageRoleAndAltText($product_sku_key, $metaProperty_Collections, $image)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'sku_id' => $product_sku_key,
            'metaProperty_Collections' => $metaProperty_Collections,
            'bynder_changes_details' => $image
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);

        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-update-bynderImageRole-and-altText');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-update-bynderImageRole-and-altText', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Change Bynder Assets Details
     *
     * @param array $bynder_auth
     * @return $this
     */
    public function changeBynderAssetsDetails($bynder_auth)
    {
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $bynder_auth['bynderDomain'],
            'permanent_token' => $bynder_auth['token'],
            'new_value_obj' => $bynder_auth['new_value_obj'],
            'base_url' => $this->_storeManager->getStore()->getBaseUrl(),
            'licence_token' => $this->getLicenceToken(),
            'bynder_metaproperty_collection' => $bynder_auth['collection_data_value']
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);
        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-sync-assets-details');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-sync-assets-details', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Change Popup Bynder Assets Details
     *
     * @param array $bynder_auth
     * @return $this
     */
    public function changePopupBynderAssetsDetails($bynder_auth)
    {
        $getBaseUrl = $this->_storeManager->getStore()->getBaseUrl();
        $fields = [
            'domain_name' => $getBaseUrl,
            'bynder_domain' => $bynder_auth['bynderDomain'],
            'permanent_token' => $bynder_auth['token'],
            'new_value_obj' => $bynder_auth['new_value_obj'],
            'base_url' => $getBaseUrl,
            'licence_token' => $this->getLicenceToken(),
            'bynder_metaproperty_collection' => $bynder_auth['collection_data_value']
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);
        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'sync-ahfproducts-popup-assets-details');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'sync-ahfproducts-popup-assets-details', $jsonData);

        $response = $this->_curl->getBody();
       
        return $response;
    }
    /**
     * Remove Role DAM
     *
     * @param array $bynder_auth
     * @return $this
     */
    public function removeSkuOrRoleDAM($bynder_auth)
    {
        $getBaseUrl = $this->_storeManager->getStore()->getBaseUrl();
        $fields = [
            'domain_name' => $getBaseUrl,
            'bynder_domain' => $bynder_auth['bynderDomain'],
            'permanent_token' => $bynder_auth['token'],
            'new_value_obj' => $bynder_auth['changes_details'],
            'base_url' => $getBaseUrl,
            'licence_token' => $this->getLicenceToken(),
            'bynder_metaproperty_collection' => $bynder_auth['collection_data_value']
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);
        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-remove-sku-role-from-dam');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-remove-sku-role-from-dam', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
    }
    /**
     * Remove Role DAM
     *
     * @param array $bynder_auth
     * @return $this
     */
    public function getCheckBynderSideDeleteData($bynder_auth)
    {
        $getBaseUrl = $this->_storeManager->getStore()->getBaseUrl();
        $fields = [
            'domain_name' => $this->_storeManager->getStore()->getBaseUrl(),
            'bynder_domain' => $this->getBynderDom(),
            'permanent_token' => $this->getPermanenToken(),
            'licence_token' => $this->getLicenceToken(),
            'base_url' => $getBaseUrl,
            'last_cron_time' => $bynder_auth['last_cron_time']
        ];
        $jsonData = '{}';
        $fields = json_encode($fields);
        $this->_curl->setOption(CURLOPT_URL, self::API_CALLED . 'ahfproducts-remove-assets-deleted-data-from-dam');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->setOption(CURLOPT_TIMEOUT, 0);
        $this->_curl->setOption(CURLOPT_ENCODING, '');
        $this->_curl->setOption(CURLOPT_MAXREDIRS, 10);
        $this->_curl->setOption(CURLOPT_FOLLOWLOCATION, true);
        $this->_curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->_curl->setOption(CURLOPT_POSTFIELDS, $fields);

        $this->_curl->addHeader("Content-Type", "application/json");

        $this->_curl->post(self::API_CALLED . 'ahfproducts-remove-assets-deleted-data-from-dam', $jsonData);

        $response = $this->_curl->getBody();
        return $response;
        /*
        $response = '{"status":1,"data":[{"id":"90261277-77FC-4DEB-8C5021715E9A1D38"},{"id":"D88487A1-C6E9-4202-B362AEEA91CDB0D1"}]}';
        return $response;*/
    }
    /**
     * Alias Sku
     *
     * @param string $bd_sku
     * @return $this
     */
    public function getSkuByAlias($bd_sku) 
    {
        /*$aliascollection = $this->aliasCollectionFactory->create();
        $aliascollection->addFieldToFilter('sku', $bd_sku);
        $aliascollection->setPageSize(1);
        $aliasSku = $aliascollection->getFirstItem()->getAliasSku();
        return $aliasSku;*/
        $alias = $this->aliasRepository->resolveForIndex($bd_sku);
        if (!$alias) {
            return $alias;
        }
        $aliasSku = $alias->getAliasSku();
        return $aliasSku;
    }
    /**
     * Alias Sku
     *
     * @param string $bd_sku
     * @return $this
     */
    public function getAliasBySku($alias_sku) 
    {
        $aliascollection = $this->aliasCollectionFactory->create();
        $aliascollection->addFieldToFilter('alias_sku', $alias_sku);
        $aliascollection->setPageSize(1);
        $sku = $aliascollection->getFirstItem()->getSku();
        return $sku;
    }

}
