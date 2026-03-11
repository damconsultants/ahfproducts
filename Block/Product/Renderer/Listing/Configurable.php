<?php

namespace DamConsultants\Ahfproducts\Block\Product\Renderer\Listing;

use Magento\Catalog\Model\Product;
use Magento\Swatches\Model\Swatch;

class Configurable extends \Magento\Swatches\Block\Product\Renderer\Listing\Configurable
{
    /**
     * Override the `getVariationMedia` method.
     *
     * @param string $attributeCode
     * @param string $optionId
     * @return array
     */
    protected function getVariationMedia($attributeCode, $optionId)
    {
        $variationProduct = $this->swatchHelper->loadFirstVariationWithSwatchImage(
            $this->getProduct(),
            [$attributeCode => $optionId]
        );

        if (!$variationProduct) {
            $variationProduct = $this->swatchHelper->loadFirstVariationWithImage(
                $this->getProduct(),
                [$attributeCode => $optionId]
            );
        }

        $variationMediaArray = [];
        if ($variationProduct) {

            if ($this->getRequest()->getFullActionName() == 'catalog_category_view') {
                $bynderImage = $variationProduct->getBynderMultiImg();
                $use_bynder_both_image = $variationProduct->getUseBynderBothImage();
                $use_bynder_cdn = $variationProduct->getUseBynderCdn();
                if ($use_bynder_cdn == 1 || $use_bynder_both_image == 1) {
                    if ($bynderImage) {
                        $decodedBynderImages = json_decode($bynderImage, true);
                        if (is_array($decodedBynderImages)) {
                            foreach ($decodedBynderImages as $key => $bynderImage) {
                                if ($bynderImage['item_type'] == 'IMAGE' && isset($bynderImage['image_role'])) {
                                    foreach ($bynderImage['image_role'] as $image_role) {
                                        if ($image_role == 'Swatch') {
                                            $variationMediaArray = [
                                                'value' => $bynderImage['thum_url'],
                                                'thumb' => $bynderImage['thum_url']
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $variationMediaArray = [
                            'value' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_IMAGE_NAME),
                            'thumb' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_THUMBNAIL_NAME),
                        ];
                    }
                } else {
                    $variationMediaArray = [
                        'value' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_IMAGE_NAME),
                        'thumb' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_THUMBNAIL_NAME),
                    ];
                }
            } else {
                $variationMediaArray = [
                    'value' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_IMAGE_NAME),
                    'thumb' => $this->getSwatchProductImage($variationProduct, Swatch::SWATCH_THUMBNAIL_NAME),
                ];
            }
        }
        return $variationMediaArray;
    }
}
