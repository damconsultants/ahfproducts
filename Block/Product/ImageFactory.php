<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace DamConsultants\Ahfproducts\Block\Product;

use Magento\Catalog\Block\Product\Image as ImageBlock;
use Magento\Catalog\Model\View\Asset\ImageFactory as AssetImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\View\Asset\PlaceholderFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\ConfigInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;

/**
 * Create imageBlock from product and view.xml
 *
 * @api
 */
class ImageFactory extends \Magento\Catalog\Block\Product\ImageFactory
{
    /**
     * @var ConfigInterface
     */
    private $presentationConfig;

    /**
     * @var AssetImageFactory
     */
    private $viewAssetImageFactory;

    /**
     * @var ParamsBuilder
     */
    private $imageParamsBuilder;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PlaceholderFactory
     */
    private $viewAssetPlaceholderFactory;
    
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

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
     * @param ObjectManagerInterface $objectManager
     * @param ConfigInterface $presentationConfig
     * @param AssetImageFactory $viewAssetImageFactory
     * @param PlaceholderFactory $viewAssetPlaceholderFactory
     * @param ProductRepositoryInterface $productRepository
     * @param ParamsBuilder $imageParamsBuilder
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param CompanyManagementInterface $companyManagement
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ConfigInterface $presentationConfig,
        AssetImageFactory $viewAssetImageFactory,
        PlaceholderFactory $viewAssetPlaceholderFactory,
        ProductRepositoryInterface $productRepository,
        ParamsBuilder $imageParamsBuilder,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement
    ) {
        $this->objectManager = $objectManager;
        $this->presentationConfig = $presentationConfig;
        $this->viewAssetPlaceholderFactory = $viewAssetPlaceholderFactory;
        $this->viewAssetImageFactory = $viewAssetImageFactory;
        $this->imageParamsBuilder = $imageParamsBuilder;
        $this->productRepository = $productRepository;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
    }

    /**
     * Remove class from custom attributes
     *
     * @param array $attributes
     * @return array
     */
    private function filterCustomAttributes(array $attributes): array
    {
        if (isset($attributes['class'])) {
            unset($attributes['class']);
        }
        return $attributes;
    }

    /**
     * Retrieve image class for HTML element
     *
     * @param array $attributes
     * @return string
     */
    private function getClass(array $attributes): string
    {
        return $attributes['class'] ?? 'product-image-photo';
    }

    /**
     * Calculate image ratio
     *
     * @param int $width
     * @param int $height
     * @return float
     */
    private function getRatio(int $width, int $height): float
    {
        if ($width && $height) {
            return $height / $width;
        }
        return 1.0;
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
     * Get Bynder image URL by role with authorization check
     *
     * @param array $imageData
     * @param string $sku
     * @param string $role
     * @param array|null $customerData
     * @return string|null
     */
    private function getBynderImageByRole(array $imageData, string $sku, string $role, $customerData): ?string
    {
        // First try to find image for specific SKU with the role
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
     * Get Bynder image URL by role (any SKU) with authorization check
     *
     * @param array $imageData
     * @param string $role
     * @param array|null $customerData
     * @return string|null
     */
    private function getBynderImageByRoleAnySku(array $imageData, string $role, $customerData): ?string
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
     * Get any Bynder image URL for specific SKU with authorization check
     *
     * @param array $imageData
     * @param string $sku
     * @param array|null $customerData
     * @return string|null
     */
    private function getAnyBynderImage(array $imageData, string $sku, $customerData): ?string
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
     * Get any Bynder image URL (any SKU) with authorization check
     *
     * @param array $imageData
     * @param array|null $customerData
     * @return string|null
     */
    private function getAnyBynderImageAnySku(array $imageData, $customerData): ?string
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
     * Get Bynder image URL with authorization
     *
     * @param Product $product
     * @param string $imageType
     * @param array|null $customerData
     * @return string|null
     */
    private function getBynderImageUrl(Product $product, string $imageType, $customerData): ?string
    {
        $productDetails = $this->productRepository->getById($product->getId());
        $bynderImage = $productDetails->getBynderMultiImg();
        $useBynderCdn = $productDetails->getUseBynderCdn();
        
        if ($useBynderCdn != 1 || empty($bynderImage)) {
            return null;
        }
        
        $jsonValue = json_decode($bynderImage, true);
        if (!is_array($jsonValue) || empty($jsonValue)) {
            return null;
        }
        
        $productSku = $product->getSku();
        $imageUrl = null;
        
        // Priority 1: Try to get image with 'Small' role for specific SKU (authorized)
        $imageUrl = $this->getBynderImageByRole($jsonValue, $productSku, 'Small', $customerData);
        
        // Priority 2: Try to get image with 'Small' role (any SKU) (authorized)
        if (empty($imageUrl)) {
            $imageUrl = $this->getBynderImageByRoleAnySku($jsonValue, 'Small', $customerData);
        }
        
        // Priority 3: Try to get image with 'Thumbnail' role for specific SKU (authorized)
        if (empty($imageUrl)) {
            $imageUrl = $this->getBynderImageByRole($jsonValue, $productSku, 'Thumbnail', $customerData);
        }
        
        // Priority 4: Try to get image with 'Thumbnail' role (any SKU) (authorized)
        if (empty($imageUrl)) {
            $imageUrl = $this->getBynderImageByRoleAnySku($jsonValue, 'Thumbnail', $customerData);
        }
        
        // Priority 5: Try to get image with 'Base' role for specific SKU (authorized)
        if (empty($imageUrl)) {
            $imageUrl = $this->getBynderImageByRole($jsonValue, $productSku, 'Base', $customerData);
        }
        
        // Priority 6: Try to get image with 'Base' role (any SKU) (authorized)
        if (empty($imageUrl)) {
            $imageUrl = $this->getBynderImageByRoleAnySku($jsonValue, 'Base', $customerData);
        }
        
        // Priority 7: Try to get any image for specific SKU (authorized)
        if (empty($imageUrl)) {
            $imageUrl = $this->getAnyBynderImage($jsonValue, $productSku, $customerData);
        }
        
        // Priority 8: Try to get any image (any SKU) (authorized)
        if (empty($imageUrl)) {
            $imageUrl = $this->getAnyBynderImageAnySku($jsonValue, $customerData);
        }
        
        return $imageUrl;
    }

    /**
     * Get image label with authorization
     *
     * @param Product $product
     * @param string $imageType
     * @param array|null $customerData
     * @return string
     */
    private function getLabel(Product $product, string $imageType, $customerData): string
    {
        $productDetails = $this->productRepository->getById($product->getId());
        $bynderImage = $productDetails->getBynderMultiImg();
        $useBynderCdn = $productDetails->getUseBynderCdn();
        $label = "";
        
        if ($useBynderCdn == 1 && !empty($bynderImage)) {
            $jsonValue = json_decode($bynderImage, true);
            $productSku = $product->getSku();
            
            if (is_array($jsonValue) && !empty($jsonValue)) {
                // Try to get alt_text for specific SKU (authorized)
                if (isset($jsonValue[$productSku]) && is_array($jsonValue[$productSku])) {
                    foreach ($jsonValue[$productSku] as $image) {
                        if ($this->isImageAuthorized($image, $customerData) && !empty($image['alt_text'])) {
                            return trim($image['alt_text']);
                        }
                    }
                }
                
                // Fallback: Try to get alt_text from any SKU (authorized)
                foreach ($jsonValue as $sku => $images) {
                    if (is_array($images)) {
                        foreach ($images as $image) {
                            if ($this->isImageAuthorized($image, $customerData) && !empty($image['alt_text'])) {
                                return trim($image['alt_text']);
                            }
                        }
                    }
                }
            }
        }
        
        // Fallback to default label
        $label = $product->getData($imageType . '_' . 'label');
        if (empty($label)) {
            $label = $product->getName();
        }
        
        return (string) $label;
    }

    /**
     * Create image block from product
     *
     * @param Product $product
     * @param string $imageId
     * @param array|null $attributes
     * @return ImageBlock
     */
    public function create(Product $product, string $imageId, ?array $attributes = null): ImageBlock
    {
        $viewImageConfig = $this->presentationConfig->getViewConfig()->getMediaAttributes(
            'Magento_Catalog',
            ImageHelper::MEDIA_TYPE_CONFIG_NODE,
            $imageId
        );
        $imageMiscParams = $this->imageParamsBuilder->build($viewImageConfig);
        $originalFilePath = $product->getData($imageMiscParams['image_type']);

        if ($originalFilePath === null || $originalFilePath === 'no_selection') {
            $imageAsset = $this->viewAssetPlaceholderFactory->create(
                [
                    'type' => $imageMiscParams['image_type']
                ]
            );
        } else {
            $imageAsset = $this->viewAssetImageFactory->create(
                [
                    'miscParams' => $imageMiscParams,
                    'filePath' => $originalFilePath,
                ]
            );
        }
        
        $attributes = $attributes === null ? [] : $attributes;
        $imageUrl = $imageAsset->getUrl();
        
        // Get customer data for authorization
        $customerData = $this->getCustomerData();
        
        // Try to get Bynder image URL with authorization
        $bynderImageUrl = $this->getBynderImageUrl($product, $imageMiscParams['image_type'] ?? '', $customerData);
        if (!empty($bynderImageUrl)) {
            $imageUrl = $bynderImageUrl;
        }
        
        $data = [
            'data' => [
                'template' => 'Magento_Catalog::product/image_with_borders.phtml',
                'image_url' => $imageUrl,
                'width' => $imageMiscParams['image_width'],
                'height' => $imageMiscParams['image_height'],
                'label' => $this->getLabel($product, $imageMiscParams['image_type'] ?? '', $customerData),
                'ratio' => $this->getRatio($imageMiscParams['image_width'] ?? 0, $imageMiscParams['image_height'] ?? 0),
                'custom_attributes' => $this->filterCustomAttributes($attributes),
                'class' => $this->getClass($attributes),
                'product_id' => $product->getId()
            ],
        ];
        
        return $this->objectManager->create(ImageBlock::class, $data);
    }
}