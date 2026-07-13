<?php

namespace DamConsultants\Ahfproducts\Plugin\Product;

use Magento\Catalog\Block\Product\View\Gallery;
use Magento\Framework\DataObject;
use Magento\Framework\AuthorizationInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Company\Api\CompanyManagementInterface;

class GalleryPlugin
{
    protected $authorization;
    protected $customerSession;
    protected $customerRepository;
    protected $companyManagement;

    public function __construct(
        AuthorizationInterface $authorization,
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        CompanyManagementInterface $companyManagement
    ) {
        $this->authorization = $authorization;
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
     * Modify the gallery JSON data
     *
     * @param Gallery $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundGetGalleryImagesJson(Gallery $subject, callable $proceed)
    {
        $product = $subject->getProduct();
        $useBynderCdn = $product->getData('use_bynder_cdn');
        $useBynderBothImage = $product->getData('use_bynder_both_image');
        $imagesItems = [];

        // Get customer data
        $customerData = $this->getCustomerData();

        if (!$this->authorization->isAllowed('DamConsultants_BynderDemo::manage_product_attribute')) {
            $bynderMultiImg = $product->getData('bynder_multi_img');
            
            if (!empty($bynderMultiImg)) {
                $bynderImages = json_decode($bynderMultiImg, true);
                
                if (is_array($bynderImages) && !empty($bynderImages)) {
                    $mainImageRole = ($useBynderBothImage == 1) ? 'image' : 'Base';
                    
                    // Process each SKU's images
                    foreach ($bynderImages as $sku => $imagesData) {
                        if (!is_array($imagesData) || empty($imagesData)) {
                            continue;
                        }

                        // Sort images for this SKU by is_order
                        usort($imagesData, function ($a, $b) {
                            $orderA = isset($a['is_order']) ? (int)$a['is_order'] : 0;
                            $orderB = isset($b['is_order']) ? (int)$b['is_order'] : 0;
                            return $orderA <=> $orderB;
                        });

                        foreach ($imagesData as $key => $values) {
                            // Skip if not an image
                            if (!isset($values['item_type']) || $values['item_type'] !== 'IMAGE') {
                                continue;
                            }

                            $imageValues = isset($values['thum_url']) ? trim($values['thum_url']) : '';
                            
                            if (empty($imageValues)) {
                                continue;
                            }

                            // Check if image is authorized for this customer
                            if (!$this->isImageAuthorized($values, $customerData)) {
                                continue; // Skip this image if not authorized
                            }

                            $isMain = false;
                            if (isset($values['image_role']) && is_array($values['image_role'])) {
                                foreach ($values['image_role'] as $imageRole) {
                                    if ($imageRole == $mainImageRole) {
                                        $isMain = true;
                                        break;
                                    }
                                }
                            }

                            $imageItem = new DataObject([
                                'thumb' => $imageValues,
                                'img' => $imageValues,
                                'full' => $imageValues,
                                'caption' => isset($values['alt_text']) ? $values['alt_text'] : $product->getName(),
                                'position' => isset($values['is_order']) ? (int)$values['is_order'] : $key + 1,
                                'isMain' => $isMain,
                                'type' => 'image',
                                'videoUrl' => null,
                                'src' => null,
                                'sku' => $sku,
                                'alias_identifier' => isset($values['all_alias_identifier']) ? $values['all_alias_identifier'] : '',
                                'is_authorized' => true
                            ]);

                            $imagesItems[] = $imageItem->toArray();
                        }
                    }
                }
            }
        }

        // If no images found or authorization check failed, fallback to default gallery
        if (empty($imagesItems)) {
            $result = $proceed();
            $existingImages = json_decode($result, true);
            if (!empty($existingImages) && is_array($existingImages)) {
                $imagesItems = $existingImages;
            }
        }

        return json_encode($imagesItems);
    }
}