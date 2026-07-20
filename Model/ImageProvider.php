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
use DamConsultants\Ahfproducts\Helper\Data;

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
    protected $dataHelper;
    private const DEFAULT_IMAGE = 'https://i0.wp.com/picjumbo.com/wp-content/uploads/silhouettes-of-hawaiian-palms-at-a-gorgeous-sunset-free-image.jpeg?h=800&quality=80';


    public function __construct(
        CartItemRepositoryInterface $itemRepository,
        ItemPoolInterface $itemPool,
        ProductRepositoryInterface $productRepository,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement,
        Data $dataHelper,
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
        $this->dataHelper = $dataHelper;
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

    private function getImageUrl(array $jsonData, ?string $sku): ?string
    {
        if (empty($sku) || !isset($jsonData[$sku])) {
            return null;
        }

        $roles = ['Thumbnail', 'Base', 'Small'];

        foreach ($roles as $role) {
            foreach ($jsonData[$sku] as $image) {
                if (
                    isset($image['image_role']) &&
                    is_array($image['image_role']) &&
                    in_array($role, $image['image_role']) &&
                    !empty($image['thum_url'])
                ) {
                    return trim($image['thum_url']);
                }
            }
        }

        foreach ($jsonData[$sku] as $image) {
            if (
                ($image['item_type'] ?? '') === 'IMAGE' &&
                !empty($image['thum_url'])
            ) {
                return trim($image['thum_url']);
            }
        }

        return null;
    }

    private function getImageAlt(array $jsonData, ?string $sku): ?string
    {
        if (empty($sku) || !isset($jsonData[$sku])) {
            return null;
        }

        foreach ($jsonData[$sku] as $image) {
            if (!empty($image['alt_text'])) {
                return trim($image['alt_text']);
            }
        }

        return null;
    }

    private function getProductImageData(\Magento\Quote\Model\Quote\Item $cartItem)
    {
        $product = $this->itemResolver->getFinalProduct($cartItem);

        $imageHelper = $this->imageHelper->init(
            $product,
            'mini_cart_product_thumbnail'
        );

        // Default Magento image
        $imageUrl = $imageHelper->getUrl();
        $imageAlt = $imageHelper->getLabel();

        $product = $this->productRepository->getById($product->getId());

        $bynderImage = $product->getData('bynder_multi_img');

        if (empty($bynderImage)) {
            return [
                'src'    => $imageUrl,
                'alt'    => $imageAlt,
                'width'  => $imageHelper->getWidth(),
                'height' => $imageHelper->getHeight(),
            ];
        }

        $jsonData = json_decode($bynderImage, true);

        if (!is_array($jsonData) || empty($jsonData)) {
            return [
                'src'    => $imageUrl,
                'alt'    => $imageAlt,
                'width'  => $imageHelper->getWidth(),
                'height' => $imageHelper->getHeight(),
            ];
        }

        $originalSku = $product->getSku();
        $displaySku  = $originalSku;

        $customerData = $this->getCustomerData();

        if (!empty($customerData)) {

            $aliasSku = $this->dataHelper->getAliasSkubyaliasidentifier(
                $originalSku,
                $customerData
            );

            if (!empty($aliasSku)) {
                $displaySku = $aliasSku;
            }
        }
        $customImage = $this->getImageUrl($jsonData, $displaySku);
        $customAlt   = $this->getImageAlt($jsonData, $displaySku);
        if (!$customImage && $displaySku !== $originalSku) {

            $customImage = $this->getImageUrl(
                $jsonData,
                $originalSku
            );

            $customAlt = $this->getImageAlt(
                $jsonData,
                $originalSku
            );
        }
        if ($customImage) {
            $imageUrl = $customImage;
        } else {
            $imageUrl = self::DEFAULT_IMAGE;
        }

        if ($customAlt) {
            $imageAlt = $customAlt;
        }

        return [
            'src'    => $imageUrl,
            'alt'    => $imageAlt,
            'width'  => $imageHelper->getWidth(),
            'height' => $imageHelper->getHeight(),
        ];
    }
}