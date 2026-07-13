<?php

namespace DamConsultants\Ahfproducts\Model;

use Magento\Checkout\Model\Cart\ImageProvider as CoreImageProvider;
use Magento\Checkout\CustomerData\DefaultItem;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Checkout\CustomerData\ItemPoolInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;

class ImageProvider extends CoreImageProvider
{
    protected $itemRepository;
    protected $itemPool;
    protected $customerDataItem;
    private $imageHelper;
    private $itemResolver;
    private $productRepository;
    private $customerSession;
    private $customerRepository;
    private $companyManagement;

    public function __construct(
        CartItemRepositoryInterface $itemRepository,
        ItemPoolInterface $itemPool,
        ProductRepositoryInterface $productRepository,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement,
        ?DefaultItem $customerDataItem = null,
        ?Image $imageHelper = null,
        ?ItemResolverInterface $itemResolver = null
    ) {
        $this->itemRepository = $itemRepository;
        $this->itemPool = $itemPool;
        $this->productRepository = $productRepository;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
        $this->customerDataItem = $customerDataItem ?: ObjectManager::getInstance()->get(DefaultItem::class);
        $this->imageHelper = $imageHelper ?: ObjectManager::getInstance()->get(Image::class);
        $this->itemResolver = $itemResolver ?: ObjectManager::getInstance()->get(ItemResolverInterface::class);
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
     * Get authorized Bynder image URL
     *
     * @param array $jsonData
     * @param string $sku
     * @param array|null $customerData
     * @return string|null
     */
    private function getAuthorizedImageUrl($jsonData, $sku, $customerData)
    {
        if (empty($jsonData) || !is_array($jsonData)) {
            return null;
        }

        // First try to get images for specific SKU
        $imagesToCheck = [];
        if (isset($jsonData[$sku]) && is_array($jsonData[$sku])) {
            $imagesToCheck = $jsonData[$sku];
        } else {
            // If no SKU-specific images, flatten all images
            foreach ($jsonData as $skuKey => $images) {
                if (is_array($images)) {
                    $imagesToCheck = array_merge($imagesToCheck, $images);
                }
            }
        }

        // Sort images by is_order
        usort($imagesToCheck, function ($a, $b) {
            $orderA = isset($a['is_order']) ? (int)$a['is_order'] : 0;
            $orderB = isset($b['is_order']) ? (int)$b['is_order'] : 0;
            return $orderA <=> $orderB;
        });

        // Priority 1: Thumbnail role
        foreach ($imagesToCheck as $imageData) {
            if (!$this->isImageAuthorized($imageData, $customerData)) {
                continue;
            }

            if (isset($imageData['image_role']) && is_array($imageData['image_role'])) {
                if (in_array('Thumbnail', $imageData['image_role'])) {
                    if (isset($imageData['thum_url']) && !empty(trim($imageData['thum_url']))) {
                        return trim($imageData['thum_url']);
                    }
                }
            }
        }

        // Priority 2: Base role
        foreach ($imagesToCheck as $imageData) {
            if (!$this->isImageAuthorized($imageData, $customerData)) {
                continue;
            }

            if (isset($imageData['image_role']) && is_array($imageData['image_role'])) {
                if (in_array('Base', $imageData['image_role'])) {
                    if (isset($imageData['thum_url']) && !empty(trim($imageData['thum_url']))) {
                        return trim($imageData['thum_url']);
                    }
                }
            }
        }

        // Priority 3: Small role
        foreach ($imagesToCheck as $imageData) {
            if (!$this->isImageAuthorized($imageData, $customerData)) {
                continue;
            }

            if (isset($imageData['image_role']) && is_array($imageData['image_role'])) {
                if (in_array('Small', $imageData['image_role'])) {
                    if (isset($imageData['thum_url']) && !empty(trim($imageData['thum_url']))) {
                        return trim($imageData['thum_url']);
                    }
                }
            }
        }

        // Priority 4: Any image
        foreach ($imagesToCheck as $imageData) {
            if (!$this->isImageAuthorized($imageData, $customerData)) {
                continue;
            }

            if (isset($imageData['item_type']) && $imageData['item_type'] === 'IMAGE') {
                if (isset($imageData['thum_url']) && !empty(trim($imageData['thum_url']))) {
                    return trim($imageData['thum_url']);
                }
            }
        }

        return null;
    }

    /**
     * Get authorized image alt text
     *
     * @param array $jsonData
     * @param string $sku
     * @param array|null $customerData
     * @return string|null
     */
    private function getAuthorizedImageAlt($jsonData, $sku, $customerData)
    {
        if (empty($jsonData) || !is_array($jsonData)) {
            return null;
        }

        // First try to get images for specific SKU
        $imagesToCheck = [];
        if (isset($jsonData[$sku]) && is_array($jsonData[$sku])) {
            $imagesToCheck = $jsonData[$sku];
        } else {
            foreach ($jsonData as $skuKey => $images) {
                if (is_array($images)) {
                    $imagesToCheck = array_merge($imagesToCheck, $images);
                }
            }
        }

        // Find first authorized image with alt_text
        foreach ($imagesToCheck as $imageData) {
            if ($this->isImageAuthorized($imageData, $customerData)) {
                if (isset($imageData['alt_text']) && !empty(trim($imageData['alt_text']))) {
                    return trim($imageData['alt_text']);
                }
            }
        }

        return null;
    }

    public function getImages($cartId)
    {
        $itemData = [];
        $items = $this->itemRepository->getList($cartId);

        // Get customer data for authorization
        $customerData = $this->getCustomerData();

        foreach ($items as $cartItem) {
            $itemData[$cartItem->getItemId()] = $this->getProductImageData($cartItem, $customerData);
        }

        return $itemData;
    }

    private function getProductImageData(\Magento\Quote\Model\Quote\Item $cartItem, $customerData = null)
    {
        $product = $this->itemResolver->getFinalProduct($cartItem);
        $imageHelper = $this->imageHelper->init(
            $product,
            'mini_cart_product_thumbnail'
        );

        $imageUrl = $imageHelper->getUrl();
        $imageAlt = $imageHelper->getLabel();

        $productId = $cartItem->getProduct()->getId();
        $product = $this->productRepository->getById($productId);
        $bynderImage = $product->getData('bynder_multi_img');
        $productSku = $product->getSku();

        if (!empty($bynderImage)) {
            $jsonData = json_decode($bynderImage, true);
            if (!empty($jsonData) && is_array($jsonData)) {
                // Get authorized image URL
                $authorizedImageUrl = $this->getAuthorizedImageUrl($jsonData, $productSku, $customerData);
                if ($authorizedImageUrl) {
                    $imageUrl = $authorizedImageUrl;
                }

                // Get authorized image alt text
                $authorizedImageAlt = $this->getAuthorizedImageAlt($jsonData, $productSku, $customerData);
                if ($authorizedImageAlt) {
                    $imageAlt = $authorizedImageAlt;
                }
            }
        }

        return [
            'src' => $imageUrl,
            'alt' => $imageAlt,
            'width' => $imageHelper->getWidth(),
            'height' => $imageHelper->getHeight(),
        ];
    }
}