<?php

namespace DamConsultants\Ahfproducts\Plugin\Minicart;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;
use Psr\Log\LoggerInterface;

class Image
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_registry;
    
    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $product;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var CompanyManagementInterface
     */
    protected $companyManagement;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Image constructor
     *
     * @param \Magento\Framework\Registry $Registry
     * @param \Magento\Catalog\Model\Product $product
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param CompanyManagementInterface $companyManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\Registry $Registry,
        \Magento\Catalog\Model\Product $product,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement,
        LoggerInterface $logger
    ) {
        $this->_registry = $Registry;
        $this->product = $product;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
        $this->logger = $logger;
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

            // Get customer's customer number
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

            $customerData['customer_numbers'] = array_unique($customerData['customer_numbers']);
            
            return $customerData;
        } catch (\Exception $e) {
            $this->logger->error('Error getting customer data: ' . $e->getMessage());
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
            if (isset($imageData['all_alias_identifier']) && !empty($imageData['all_alias_identifier'])) {
                return false;
            }
            return true;
        }

        // Check if image has alias identifiers
        if (isset($imageData['all_alias_identifier']) && !empty($imageData['all_alias_identifier'])) {
            $aliasIdentifiers = array_map('trim', explode(',', $imageData['all_alias_identifier']));
            
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
     * Get authorized image URL for a specific SKU
     *
     * @param array $jsonValue
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
     * Around Get Item Data
     *
     * @param \Magento\Checkout\CustomerData\AbstractItem $subject
     * @param \Closure $proceed
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return array
     */
    public function aroundGetItemData(
        \Magento\Checkout\CustomerData\AbstractItem $subject,
        \Closure $proceed,
        \Magento\Quote\Model\Quote\Item $item
    ) {
        $data = $proceed($item);
        
        try {
            $product = $item->getProduct();
            $productId = $product->getId();
            $productSku = $product->getSku();
            
            $this->logger->info('Processing product: ' . $productSku . ' (ID: ' . $productId . ')');
            
            // Load full product to get Bynder data
            $fullProduct = $this->product->load($productId);
            $bynderImage = $fullProduct->getData('bynder_multi_img');
            
            $this->logger->info('Bynder image data length: ' . strlen($bynderImage));
            
            // Get customer data for authorization
            $customerData = $this->getCustomerData();
            $this->logger->info('Customer data: ' . print_r($customerData, true));
            
            if (!empty($bynderImage)) {
                $jsonValue = json_decode($bynderImage, true);
                
                if (is_array($jsonValue) && !empty($jsonValue)) {
                    $this->logger->info('JSON decoded successfully, keys: ' . print_r(array_keys($jsonValue), true));
                    
                    // Try to get authorized image for this SKU
                    $imageUrl = $this->getAuthorizedImageUrl($jsonValue, $productSku, $customerData);
                    
                    if ($imageUrl) {
                        $data['product_image']['src'] = $imageUrl;
                        $this->logger->info('Updated image URL: ' . $imageUrl);
                    } else {
                        $this->logger->info('No authorized image found, keeping default');
                    }
                } else {
                    $this->logger->info('JSON decode failed or empty');
                }
            } else {
                $this->logger->info('No Bynder image data found for product');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in aroundGetItemData: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }
        
        return $data;
    }
}