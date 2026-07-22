<?php

namespace DamConsultants\Ahfproducts\Plugin\Wishlist\CustomerData;

use Magento\Wishlist\CustomerData\Wishlist;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;
use DamConsultants\Ahfproducts\Helper\Data;
use Magento\Catalog\Helper\Image as ImageHelper;

class WishlistPlugin
{
    private ProductRepositoryInterface $productRepository;
    private Session $customerSession;
    private CustomerRepositoryInterface $customerRepository;
    private CompanyManagementInterface $companyManagement;
    private Data $dataHelper;
    private ImageHelper $imageHelper;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement,
        Data $dataHelper,
        ImageHelper $imageHelper
    ) {
        $this->productRepository = $productRepository;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
        $this->dataHelper = $dataHelper;
        $this->imageHelper = $imageHelper;
    }

    public function afterGetSectionData(
        Wishlist $subject,
        array $result
    ) {
        if (empty($result['items'])) {
            return $result;
        }
        $customerData = $this->getCustomerData();
        foreach ($result['items'] as &$item) {
            try {
                $product = $this->productRepository->getById($item['product_id']);
                $sku = $product->getSku();
                if (!empty($customerData)) {
                    $aliasSku = $this->dataHelper->getAliasSkubyaliasidentifier(
                        $sku,
                        $customerData
                    );
                    if (!empty($aliasSku)) {
                        $sku = $aliasSku;
                    }
                }
                $image = $this->getBynderImage(
                    $product->getData('bynder_multi_img'),
                    $sku
                );
                $item['image']['src'] = $image ?: $this->getPlaceholderImage();
            } catch (\Exception $e) {
            }
        }
        return $result;
    }

    private function getBynderImage($json, $sku): ?string
    {
        if (!$json) {
            return null;
        }
        $images = json_decode($json, true);
        if (
            !is_array($images) ||
            empty($images[$sku])
        ) {
            return null;
        }
        foreach (['Thumbnail', 'Base', 'Small'] as $role) {
            foreach ($images[$sku] as $image) {
                if (
                    isset($image['image_role']) &&
                    in_array($role, $image['image_role']) &&
                    !empty($image['thum_url'])
                ) {
                    return trim($image['thum_url']);
                }
            }
        }
        foreach ($images[$sku] as $image) {
            if (
                ($image['item_type'] ?? '') === 'IMAGE' &&
                !empty($image['thum_url'])
            ) {
                return trim($image['thum_url']);
            }
        }
        return null;
    }

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
            $customerNumber = $customer->getCustomAttribute('customer_number') 
                ? $customer->getCustomAttribute('customer_number')->getValue() 
                : null;
            
            if ($customerNumber) {
                $customerData['customer_numbers'][] = $customerNumber;
            }
            $company = $this->companyManagement->getByCustomerId($customerId);           
            if ($company) {
                $customerData['company_id'] = $company->getId();
                $companyCustomerNumber = $company->getData('customer_number');
                
                if ($companyCustomerNumber) {
                    $customerData['customer_numbers'][] = $companyCustomerNumber;
                }
            }
            $customerData['customer_numbers'] = array_unique($customerData['customer_numbers']);
            
            return $customerData;
        } catch (\Exception $e) {
            return null;
        }
    }
    /**
     * Get Placeholder Image
     *
     * @return string
     */
    private function getPlaceholderImage(): string
    {
        $placeholder = $this->dataHelper->getPlaceHolderImage();

        if (!empty($placeholder)) {
            return $placeholder;
        }

        return $this->imageHelper->getDefaultPlaceholderUrl('thumbnail');
    }
}