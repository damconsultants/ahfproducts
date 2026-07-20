<?php

namespace DamConsultants\Ahfproducts\Plugin\Catalog\Block\Product;

use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;
use DamConsultants\Ahfproducts\Helper\Data;

class ImageFactoryPlugin
{
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var AssetRepository
     */
    private $assetRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CompanyManagementInterface
     */
    private $companyManagement;
    protected $datahelper;

    /**
     * Constructor
     *
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param AssetRepository $assetRepository
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param CompanyManagementInterface $companyManagement
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        AssetRepository $assetRepository,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement,
        Data $dataHelper
    ) {
        $this->productRepository = $productRepository;
        $this->assetRepository = $assetRepository;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
        $this->datahelper = $dataHelper;
    }

    /**
     * Get current customer's company and customer numbers
     *
     * @return array|null
     */
    private function getCustomerData()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            
            $customerData = [
                'customer_id' => $customerId,
                'customer_email' => $customer->getEmail(),
                'customer_numbers' => []
            ];

            // Get customer's customer number (if exists as attribute)
            $customerNumber = $customer->getCustomAttribute('customer_number') 
                ? $customer->getCustomAttribute('customer_number')->getValue() 
                : null;
            
            if ($customerNumber) {
                $customerData['customer_numbers'][] = $customerNumber;
            }

            // Get customer's company
            $company = $this->companyManagement->getByCustomerId($customerId);
            
            if ($company) {
                $customerData['company_id'] = $company->getId();
                
                // Get company's customer number
                $companyCustomerNumber = $company->getData('customer_number');
                
                if ($companyCustomerNumber) {
                    $customerData['customer_numbers'][] = $companyCustomerNumber;
                }
            }

            // Remove duplicates
            $customerData['customer_numbers'] = array_unique($customerData['customer_numbers']);
            
            return $customerData;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find image URL by role and SKU with authorization check
     *
     * @param array $imageData
     * @param string $sku
     * @param string $role
     * @return string|null
     */
    private function findImageByRole($imageData,$sku,$role)
    {
        if (isset($imageData[$sku]) && is_array($imageData[$sku])) {
            foreach ($imageData[$sku] as $image) {

                if (isset($image['image_role']) && 
                    is_array($image['image_role']) && 
                    in_array($role, $image['image_role']) &&
                    isset($image['thum_url']) &&
                    !empty(trim($image['thum_url']))) {
                    return trim($image['thum_url']);
                }
            }
        }
        return null;
    }

    /**
     * Find any image for SKU with authorization check
     *
     * @param array $imageData
     * @param string $sku
     * @return string|null
     */
    private function findAnyImage($imageData,$sku)
    {
        if (isset($imageData[$sku]) && is_array($imageData[$sku])) {
            foreach ($imageData[$sku] as $image) {

                if (isset($image['item_type']) && 
                    $image['item_type'] === 'IMAGE' &&
                    isset($image['thum_url']) &&
                    !empty(trim($image['thum_url']))) {
                    return trim($image['thum_url']);
                }
            }
        }
        return null;
    }

    /**
     * Plugin for ImageFactory::create()
     *
     * @param ImageFactory $subject
     * @param \Closure $proceed
     * @param Product $product
     * @param string $imageId
     * @param array|null $attributes
     * @return \Magento\Catalog\Block\Product\Image
     * @throws NoSuchEntityException
     */
    public function aroundCreate(
        ImageFactory $subject,
        \Closure $proceed,
        Product $product,
        string $imageId,
        ?array $attributes = null
    ) {
        $attributes = $attributes ?? [];
    
        // Use the loaded product instead of reloading from repository
        $useBynderCdn = (bool)$product->getData('use_bynder_cdn');
        $bynderImages = $product->getData('bynder_multi_img');
    
        $imageUrl = null;
    
        if ($useBynderCdn && !empty($bynderImages)) {
    
            $imageData = json_decode($bynderImages, true);
    
            $productSku = $product->getSku();
    
            // Get customer data
            $customerData = $this->getCustomerData();
    
            if (!empty($customerData)) {
                $aliasSku = $this->datahelper->getAliasSkubyaliasidentifier(
                    $productSku,
                    $customerData
                );
    
                if (!empty($aliasSku)) {
                    $productSku = $aliasSku;
                }
            }
    
            if (is_array($imageData)) {
    
                $imageUrl = $this->findImageByRole($imageData, $productSku, 'Small');
    
                if (!$imageUrl) {
                    $imageUrl = $this->findImageByRole($imageData, $productSku, 'Thumbnail');
                }
    
                if (!$imageUrl) {
                    $imageUrl = $this->findImageByRole($imageData, $productSku, 'Base');
                }
    
                if (!$imageUrl) {
                    $imageUrl = $this->findAnyImage($imageData, $productSku);
                }
            }
        }
    
        // ALWAYS set image
        if (!empty($imageUrl)) {
            $attributes['src'] = $imageUrl;
        } else {
            $attributes['src'] = $this->datahelper->getPlaceHolderImage();
        }
    
        return $proceed($product, $imageId, $attributes);
    }
}