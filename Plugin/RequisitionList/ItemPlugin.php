<?php

namespace DamConsultants\Ahfproducts\Plugin\RequisitionList;

use Magento\RequisitionList\Block\Requisition\View\Item;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;
use DamConsultants\Ahfproducts\Helper\Data;

class ItemPlugin
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private Session $customerSession,
        private CustomerRepositoryInterface $customerRepository,
        private CompanyManagementInterface $companyManagement,
        private Data $dataHelper
    ) {
    }

    public function afterGetImageUrl(
        Item $subject,
        ?string $result
    ): ?string {

        $item = $subject->getItem();

        if (!$item) {
            return $result;
        }

        try {
            $productSku = $item->getSku();
            $product = $this->productRepository->get($productSku);

            if (!$product || !$product->getId()) {
                return $result;
            }

            $json = $product->getData('bynder_multi_img');

            if (empty($json)) {
                return $this->getPlaceholderOrMagento($result);
            }

            $images = json_decode($json, true);

            if (!is_array($images)) {
                return $this->getPlaceholderOrMagento($result);
            }

            $originalSku = $product->getSku();
            $displaySku = $originalSku;

            if ($customerData = $this->getCustomerData()) {
                $aliasSku = $this->dataHelper->getAliasSkubyaliasidentifier(
                    $originalSku,
                    $customerData
                );

                if (!empty($aliasSku)) {
                    $displaySku = $aliasSku;
                }
            }

            $image = $this->findImage($images, $displaySku);

            if (!$image && $displaySku !== $originalSku) {
                $image = $this->findImage($images, $originalSku);
            }

            if ($image) {
                return $image;
            }

            return $this->getPlaceholderOrMagento($result);

        } catch (\Exception $e) {
            return $result;
        }
    }

    /**
     * Return custom placeholder if configured,
     * otherwise return Magento's original image/placeholder.
     */
    private function getPlaceholderOrMagento(?string $result): ?string
    {
        $placeholder = trim((string)$this->dataHelper->getPlaceHolderImage());

        if (!empty($placeholder)) {
            return $placeholder;
        }

        return $result;
    }

    private function findImage(array $images, string $sku): ?string
    {
        if (!isset($images[$sku])) {
            return null;
        }

        foreach (['Thumbnail', 'Small', 'Base'] as $role) {
            foreach ($images[$sku] as $image) {
                if (
                    !empty($image['thum_url']) &&
                    isset($image['image_role']) &&
                    is_array($image['image_role']) &&
                    in_array($role, $image['image_role'])
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

    private function getCustomerData(): ?array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

        try {
            $customerId = $this->customerSession->getCustomerId();

            $customer = $this->customerRepository->getById($customerId);

            $data = [
                'customer_numbers' => []
            ];

            if ($customer->getCustomAttribute('customer_number')) {
                $data['customer_numbers'][] =
                    $customer->getCustomAttribute('customer_number')->getValue();
            }

            $company = $this->companyManagement->getByCustomerId($customerId);

            if ($company && $company->getData('customer_number')) {
                $data['customer_numbers'][] =
                    $company->getData('customer_number');
            }

            $data['customer_numbers'] = array_unique($data['customer_numbers']);

            return $data;

        } catch (\Exception $e) {
            return null;
        }
    }
}