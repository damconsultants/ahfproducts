<?php

namespace DamConsultants\Ahfproducts\Plugin\Minicart;

use Magento\Checkout\CustomerData\AbstractItem;
use Magento\Quote\Model\Quote\Item;

class Image
{
    /**
     * Around Get Item Data
     *
     * @param AbstractItem $subject
     * @param \Closure $proceed
     * @param Item $item
     * @return array
     */
    public function aroundGetItemData(
        AbstractItem $subject,
        \Closure $proceed,
        Item $item
    ) {
        $data = $proceed($item);

        /** Product is already loaded – DO NOT load again */
        $product = $item->getProduct();

        if (!$product || !$product->getId()) {
            return $data;
        }

        /** Get Bynder image JSON safely */
        $bynderImage = (string) $product->getData('bynder_multi_img');

        if ($bynderImage === '') {
            return $data;
        }

        $images = json_decode($bynderImage, true);

        if (!is_array($images)) {
            return $data;
        }

        /** Find Thumbnail image */
        foreach ($images as $image) {
            if (
                !empty($image['image_role']) &&
                is_array($image['image_role']) &&
                in_array('Thumbnail', $image['image_role'], true) &&
                !empty($image['thum_url'])
            ) {
                $data['product_image']['src'] = trim($image['thum_url']);
                break;
            }
        }

        return $data;
    }
}
