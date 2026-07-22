<?php

namespace DamConsultants\Ahfproducts\Plugin\Product;

use Magento\Catalog\Block\Product\View\Gallery;
use Magento\Framework\DataObject;
use Magento\Framework\AuthorizationInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;
use DamConsultants\Ahfproducts\Helper\Data;
use Magento\Catalog\Helper\Image as ImageHelper;

class GalleryPlugin
{
    protected $authorization;
    protected $customerSession;
    protected $customerRepository;
    protected $companyManagement;
    protected $datahelper;
    private $imageHelper;

    public function __construct(
        AuthorizationInterface $authorization,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement,
        Data $DataHelper,
        ImageHelper $imageHelper
    ) {
        $this->authorization = $authorization;
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->companyManagement = $companyManagement;
        $this->datahelper = $DataHelper;
        $this->imageHelper = $imageHelper;
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
     * Modify the gallery JSON data
     *
     * @param Gallery $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundGetGalleryImagesJson(Gallery $subject, callable $proceed)
    {
        $product = $subject->getProduct();
        $useBynderBothImage = $product->getData('use_bynder_both_image');

        $imagesItems = [];

        $customerData = $this->getCustomerData();

        $displaySku = $product->getSku();

        if (!empty($customerData)) {
            $aliasSku = $this->datahelper->getAliasSkubyaliasidentifier(
                $product->getSku(),
                $customerData
            );

            if (!empty($aliasSku)) {
                $displaySku = $aliasSku;
            }
        }

        if (!$this->authorization->isAllowed('DamConsultants_BynderDemo::manage_product_attribute')) {

            $bynderMultiImg = $product->getData('bynder_multi_img');

            if (!empty($bynderMultiImg)) {

                $bynderImages = json_decode($bynderMultiImg, true);

                if (is_array($bynderImages)) {

                    $mainImageRole = ($useBynderBothImage == 1) ? 'image' : 'Base';

                    foreach ($bynderImages as $sku => $imagesData) {

                        if ($sku != $displaySku) {
                            continue;
                        }

                        if (!is_array($imagesData) || empty($imagesData)) {
                            continue;
                        }

                        usort($imagesData, function ($a, $b) {
                            return ((int)($a['is_order'] ?? 0)) <=> ((int)($b['is_order'] ?? 0));
                        });

                        foreach ($imagesData as $key => $values) {

                            if (($values['item_type'] ?? '') !== 'IMAGE') {
                                continue;
                            }

                            $imageUrl = trim($values['thum_url'] ?? '');

                            if ($imageUrl === '') {
                                continue;
                            }

                            $isMain = false;

                            if (!empty($values['image_role']) && is_array($values['image_role'])) {
                                $isMain = in_array($mainImageRole, $values['image_role']);
                            }

                            $imagesItems[] = [
                                'thumb'     => $imageUrl,
                                'img'       => $imageUrl,
                                'full'      => $imageUrl,
                                'caption'   => $values['alt_text'] ?? $product->getName(),
                                'position'  => (int)($values['is_order'] ?? ($key + 1)),
                                'isMain'    => $isMain,
                                'type'      => 'image',
                                'videoUrl'  => null,
                                'src'       => null,
                                'sku'       => $sku
                            ];
                        }

                        break;
                    }
                }
            }
        }

        // If Bynder images exist, use them
        if (!empty($imagesItems)) {
            return json_encode($imagesItems);
        }

        // Custom placeholder
        $placeholder = trim((string)$this->datahelper->getPlaceHolderImage());

        if (!empty($placeholder)) {

            $imagesItems[] = [
                'thumb'     => $placeholder,
                'img'       => $placeholder,
                'full'      => $placeholder,
                'caption'   => $product->getName(),
                'position'  => 1,
                'isMain'    => true,
                'type'      => 'image',
                'videoUrl'  => null,
                'src'       => null,
                'sku'       => $displaySku
            ];

            return json_encode($imagesItems);
        }

        // Otherwise use Magento default gallery/images
        return $proceed();
    }
    private function getPlaceholderImage(): string
    {
        $placeholder = $this->datahelper->getPlaceHolderImage();

        if (!empty($placeholder)) {
            return $placeholder;
        }

        return $this->imageHelper->getDefaultPlaceholderUrl('image');
    }
}