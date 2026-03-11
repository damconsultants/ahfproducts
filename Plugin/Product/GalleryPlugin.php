<?php

namespace DamConsultants\Ahfproducts\Plugin\Product;

use Magento\Catalog\Block\Product\View\Gallery;
use Magento\Framework\DataObject;
use Magento\Framework\AuthorizationInterface;

class GalleryPlugin
{
    protected $authorization;

    public function __construct(AuthorizationInterface $authorization)
    {
        $this->authorization = $authorization;
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
        if (!$this->authorization->isAllowed('DamConsultants_BynderDemo::manage_product_attribute')) {
            if ($useBynderBothImage == 1) {
                if (!empty($product->getData('bynder_multi_img'))) {
                    $bynderImage = $product->getData('bynder_multi_img');
                    $jsonValue = json_decode($bynderImage, true);
					usort($jsonValue, function ($a, $b) {
						return $a['is_order'] <=> $b['is_order'];
					});
                    $roleImage = 0;
                    foreach ($jsonValue as $key => $values) {
                        $imageValues = trim($values['thum_url']);

                        if ($values['item_type'] == 'IMAGE' && isset($values['image_role'])) {
                            foreach ($values['image_role'] as $imageRole) {
                                if ($imageRole == 'image') {
                                    $roleImage = 1;
                                }
                            }
                        }

                        $imageItem = new DataObject([
                            'thumb' => $imageValues,
                            'img' => $imageValues,
                            'full' => $imageValues,
                            'caption' => $product->getName(),
                            'position' => $key + 1,
                            'isMain' => $roleImage,
                            'type' => ($values['item_type'] == 'IMAGE') ? 'image' : 'video',
                            'videoUrl' => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                            "src" => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                            "type" => ($values['item_type'] == 'VIDEO') ? 'iframe' : 'image'
                        ]);

                        $imagesItems[] = $imageItem->toArray();
                    }
                }
            } elseif ($useBynderCdn == 1) {
                if (!empty($product->getData('bynder_multi_img'))) {
                    $bynderImage = $product->getData('bynder_multi_img');
                    $jsonValue = json_decode($bynderImage, true);
					usort($jsonValue, function ($a, $b) {
						return $a['is_order'] <=> $b['is_order'];
					});
                    $roleImage = 0;

                    foreach ($jsonValue as $key => $values) {
                        $imageValues = trim($values['thum_url']);

                        if ($values['item_type'] == 'IMAGE' && isset($values['image_role'])) {
                            foreach ($values['image_role'] as $imageRole) {
                                if ($imageRole == 'Base') {
                                    $roleImage = 1;
                                }
                            }
                        }

                        $imageItem = new DataObject([
                            'thumb' => $imageValues,
                            'img' => $imageValues,
                            'full' => $imageValues,
                            'caption' => $product->getName(),
                            'position' => $key + 1,
                            'isMain' => $roleImage,
                            'type' => ($values['item_type'] == 'IMAGE') ? 'image' : 'video',
                            'videoUrl' => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                            "src" => ($values['item_type'] == 'VIDEO') ? $values['item_url'] : null,
                            "type" => ($values['item_type'] == 'VIDEO') ? 'iframe' : 'image'
                        ]);

                        $imagesItems[] = $imageItem->toArray();
                    }
                }
            }
        }
        // Fallback to default gallery images if not using Bynder
        if (empty($imagesItems)) {
            $result = $proceed();

            // Decode existing gallery JSON data
            $existingImages = json_decode($result, true);
            if (!empty($existingImages)) {
                $imagesItems = $existingImages;
            }
        }

        return json_encode($imagesItems);
    }
}
