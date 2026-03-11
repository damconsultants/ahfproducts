<?php
namespace DamConsultants\Ahfproducts\Block\Product\Renderer;

use Magento\Catalog\Model\Product;

class Configurable extends \Magento\Swatches\Block\Product\Renderer\Configurable
{
    protected function getSwatchProductImage(Product $childProduct, $imageType)
    {
        
        $bynderImage = $childProduct->getBynderMultiImg();
        $use_bynder_both_image = $childProduct->getUseBynderBothImage();
        $use_bynder_cdn = $childProduct->getUseBynderCdn();
        if($this->getRequest()->getFullActionName() == 'catalog_product_view') {
            if ($use_bynder_cdn == 1 || $use_bynder_both_image == 1) {
                if ($bynderImage) {
                    $decodedBynderImages = json_decode($bynderImage, true);
                    if (is_array($decodedBynderImages)) {
                        foreach ($decodedBynderImages as $key => $bynderImage) {
                            if ($bynderImage['item_type'] == 'IMAGE' && isset($bynderImage['image_role'])) {
                                foreach ($bynderImage['image_role'] as $image_role) {
                                    if ($image_role == 'Swatch') {
                                        return $bynderImage['thum_url'];
                                    }
                                }
                            }
                        }
                    }
                } else {
                    return parent::getSwatchProductImage($childProduct, $imageType);
                }
            } else {
                return parent::getSwatchProductImage($childProduct, $imageType);
            }
        } else {
            return parent::getSwatchProductImage($childProduct, $imageType);
        }
    }
}
