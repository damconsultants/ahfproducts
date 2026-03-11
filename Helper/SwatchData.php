<?php

namespace DamConsultants\Ahfproducts\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Catalog\Model\Product as ModelProduct;
use Magento\Catalog\Api\Data\ProductInterface as Product;

class SwatchData extends \Magento\Swatches\Helper\Data
{
    /**
     * Load first variation with swatch image
     *
     * @param Product $configurableProduct
     * @param array $requiredAttributes
     * @return bool|Product
     */
    public function loadFirstVariationWithSwatchImage(Product $configurableProduct, array $requiredAttributes)
    {

        if ($this->isProductHasSwatch($configurableProduct)) {
            $usedProducts = $configurableProduct->getTypeInstance()->getUsedProducts($configurableProduct);
            foreach ($usedProducts as $simpleProduct) {
                if (!array_diff_assoc($requiredAttributes, $simpleProduct->getData())
                    || $this->isMediaAvailables($simpleProduct, 'swatch_image')
                ) { 
                    return $simpleProduct;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * Load first variation with image
     *
     * @param Product $configurableProduct
     * @param array $requiredAttributes
     * @return bool|Product
     */
    public function loadFirstVariationWithImage(Product $configurableProduct, array $requiredAttributes)
    {
        if ($this->isProductHasSwatch($configurableProduct)) {
            $usedProducts = $configurableProduct->getTypeInstance()->getUsedProducts($configurableProduct);
            foreach ($usedProducts as $simpleProduct) {
                if (!array_diff_assoc($requiredAttributes, $simpleProduct->getData())
                    || $this->isMediaAvailables($simpleProduct, 'image')
                ) { 
                    return $simpleProduct;
                }
            }
        } else {
            return false;
        }
    }
    /**
     * Check is media attribute available
     *
     * @param ModelProduct $product
     * @param string $attributeCode
     * @return bool
     */
    public function isMediaAvailables(ModelProduct $product, string $attributeCode): bool
    {
        $isAvailable = false;

        $mediaGallery = $product->getMediaGalleryEntries();
        foreach ($mediaGallery as $mediaEntry) {
            if (in_array($attributeCode, $mediaEntry->getTypes(), true)) {
                $isAvailable = !$mediaEntry->isDisabled();
                break;
            }
        }

        return $isAvailable;
    }

}
