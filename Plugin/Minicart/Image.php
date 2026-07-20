<?php

namespace DamConsultants\Ahfproducts\Plugin\Minicart;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;
use Psr\Log\LoggerInterface;
use DamConsultants\Ahfproducts\Helper\Data;

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
    protected $dataHelper;
    private const DEFAULT_IMAGE = 'https://i0.wp.com/picjumbo.com/wp-content/uploads/silhouettes-of-hawaiian-palms-at-a-gorgeous-sunset-free-image.jpeg?h=800&quality=80';

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
        LoggerInterface $logger,
        Data $dataHelper,
    ) {
        $this->_registry = $Registry;
        $this->product = $product;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
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

    private function getImageUrl(array $jsonData, string $sku): ?string
    {
        if (!isset($jsonData[$sku]) || !is_array($jsonData[$sku])) {
            return null;
        }

        usort($jsonData[$sku], function ($a, $b) {
            return ((int)($a['is_order'] ?? 0)) <=> ((int)($b['is_order'] ?? 0));
        });

        $roles = ['Thumbnail', 'Base', 'Small'];

        foreach ($roles as $role) {
            foreach ($jsonData[$sku] as $image) {

                if (
                    !empty($image['image_role']) &&
                    in_array($role, $image['image_role']) &&
                    !empty($image['thum_url'])
                ) {
                    return trim($image['thum_url']);
                }
            }
        }

        foreach ($jsonData[$sku] as $image) {

            if (
                ($image['item_type'] ?? '') == 'IMAGE' &&
                !empty($image['thum_url'])
            ) {
                return trim($image['thum_url']);
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

            $customerData = $this->getCustomerData();

            if (!empty($customerData)) {
                $productSku = $this->dataHelper->getAliasSkubyaliasidentifier(
                    $productSku,
                    $customerData
                );
            }
            
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
                    $imageUrl = $this->getImageUrl(
                        $jsonValue,
                        $productSku
                    );
                    
                    if ($imageUrl) {
                        $data['product_image']['src'] = $imageUrl;
                        $this->logger->info('Updated image URL: ' . $imageUrl);
                    } else {
                        $data['product_image']['src'] = $this->dataHelper->getPlaceHolderImage();;
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