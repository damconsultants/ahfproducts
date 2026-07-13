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
        CompanyManagementInterface $companyManagement
    ) {
        $this->productRepository = $productRepository;
        $this->assetRepository = $assetRepository;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
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
     * Check if image is authorized for current customer
     *
     * @param array $imageData
     * @param array|null $customerData
     * @return bool
     */
    private function isImageAuthorized($imageData, $customerData)
    {
        // If no customer is logged in, show only images without alias restrictions
        if (empty($customerData) || empty($customerData['customer_numbers'])) {
            // Only show images that don't have alias restrictions
            if (isset($imageData['all_alias_identifier']) && !empty($imageData['all_alias_identifier'])) {
                return false;
            }
            return true;
        }

        // Check if image has alias identifiers
        if (isset($imageData['all_alias_identifier']) && !empty($imageData['all_alias_identifier'])) {
            $aliasIdentifiers = array_map('trim', explode(',', $imageData['all_alias_identifier']));
            
            // Check if any customer number matches the image's alias
            foreach ($customerData['customer_numbers'] as $customerNumber) {
                if (in_array($customerNumber, $aliasIdentifiers)) {
                    return true;
                }
            }
            return false;
        }

        // If no alias identifiers, image is visible to all
        return true;
    }

    /**
     * Find image URL by role and SKU with authorization check
     *
     * @param array $imageData
     * @param string $sku
     * @param string $role
     * @param array|null $customerData
     * @return string|null
     */
    private function findImageByRole($imageData, $sku, $role, $customerData)
    {
        if (isset($imageData[$sku]) && is_array($imageData[$sku])) {
            foreach ($imageData[$sku] as $image) {
                // Check authorization first
                if (!$this->isImageAuthorized($image, $customerData)) {
                    continue;
                }

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
     * @param array|null $customerData
     * @return string|null
     */
    private function findAnyImage($imageData, $sku, $customerData)
    {
        if (isset($imageData[$sku]) && is_array($imageData[$sku])) {
            foreach ($imageData[$sku] as $image) {
                // Check authorization first
                if (!$this->isImageAuthorized($image, $customerData)) {
                    continue;
                }

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
     * Find image URL by role across all SKUs with authorization check
     *
     * @param array $imageData
     * @param string $role
     * @param array|null $customerData
     * @return string|null
     */
    private function findImageByRoleAnySku($imageData, $role, $customerData)
    {
        foreach ($imageData as $sku => $images) {
            if (is_array($images)) {
                foreach ($images as $image) {
                    // Check authorization first
                    if (!$this->isImageAuthorized($image, $customerData)) {
                        continue;
                    }

                    if (isset($image['image_role']) && 
                        is_array($image['image_role']) && 
                        in_array($role, $image['image_role']) &&
                        isset($image['thum_url']) &&
                        !empty(trim($image['thum_url']))) {
                        return trim($image['thum_url']);
                    }
                }
            }
        }
        return null;
    }

    /**
     * Find any image across all SKUs with authorization check
     *
     * @param array $imageData
     * @param array|null $customerData
     * @return string|null
     */
    private function findAnyImageAnySku($imageData, $customerData)
    {
        foreach ($imageData as $sku => $images) {
            if (is_array($images)) {
                foreach ($images as $image) {
                    // Check authorization first
                    if (!$this->isImageAuthorized($image, $customerData)) {
                        continue;
                    }

                    if (isset($image['item_type']) && 
                        $image['item_type'] === 'IMAGE' &&
                        isset($image['thum_url']) &&
                        !empty(trim($image['thum_url']))) {
                        return trim($image['thum_url']);
                    }
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
        $productDetails = $this->productRepository->getById($product->getId());
        $useBynderCdn = $productDetails->getData('use_bynder_cdn');
        $bynderImages = $productDetails->getData('bynder_multi_img');

        // Get customer data for authorization
        $customerData = $this->getCustomerData();

        if ($useBynderCdn && !empty($bynderImages)) {
            $imageData = json_decode($bynderImages, true);
            $imageUrl = null;
            $productSku = $product->getSku();

            if (is_array($imageData) && !empty($imageData)) {
                // Priority 1: Small role for specific SKU (authorized)
                $imageUrl = $this->findImageByRole($imageData, $productSku, 'Small', $customerData);
                
                // Priority 2: Small role (any SKU) (authorized)
                if (empty($imageUrl)) {
                    $imageUrl = $this->findImageByRoleAnySku($imageData, 'Small', $customerData);
                }

                // Priority 3: Thumbnail role for specific SKU (authorized)
                if (empty($imageUrl)) {
                    $imageUrl = $this->findImageByRole($imageData, $productSku, 'Thumbnail', $customerData);
                }

                // Priority 4: Thumbnail role (any SKU) (authorized)
                if (empty($imageUrl)) {
                    $imageUrl = $this->findImageByRoleAnySku($imageData, 'Thumbnail', $customerData);
                }

                // Priority 5: Base role for specific SKU (authorized)
                if (empty($imageUrl)) {
                    $imageUrl = $this->findImageByRole($imageData, $productSku, 'Base', $customerData);
                }

                // Priority 6: Base role (any SKU) (authorized)
                if (empty($imageUrl)) {
                    $imageUrl = $this->findImageByRoleAnySku($imageData, 'Base', $customerData);
                }

                // Priority 7: Any image for specific SKU (authorized)
                if (empty($imageUrl)) {
                    $imageUrl = $this->findAnyImage($imageData, $productSku, $customerData);
                }

                // Priority 8: Any image (any SKU) (authorized)
                if (empty($imageUrl)) {
                    $imageUrl = $this->findAnyImageAnySku($imageData, $customerData);
                }
            }

            if ($imageUrl) {
                $attributes['src'] = $imageUrl;
            }
        }

        return $proceed($product, $imageId, $attributes);
    }
}