<?php

namespace DamConsultants\Ahfproducts\Plugin\Catalog\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\DataObject;
use DamConsultants\Ahfproducts\Helper\Data;

class RelatedPlugin
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var ImageHelper
     */
    private $imageHelper;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        Data $dataHelper,
        ImageHelper $imageHelper
    ) {
        $this->productRepository = $productRepository;
        $this->dataHelper = $dataHelper;
        $this->imageHelper = $imageHelper;
    }

    /**
     * After modifyData
     *
     * @param \Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\Related $subject
     * @param array $result
     * @return array
     */
    public function afterModifyData($subject, array $result)
    {
        foreach ($result as &$productData) {

            if (empty($productData['links'])) {
                continue;
            }

            foreach (['related', 'upsell', 'crosssell'] as $type) {

                if (empty($productData['links'][$type])) {
                    continue;
                }

                foreach ($productData['links'][$type] as &$item) {

                    try {
                        $product = $this->productRepository->getById((int)$item['id']);

                        $image = $this->getThumbnail($product);

                        if (!$image) {

                            $placeholder = trim((string)$this->dataHelper->getPlaceHolderImage());

                            if (!empty($placeholder)) {
                                $image = $placeholder;
                            } else {
                                // Magento default placeholder
                                $image = $this->imageHelper
                                    ->init($product, 'product_listing_thumbnail')
                                    ->getUrl();
                            }
                        }

                        $item['thumbnail'] = $image;

                    } catch (\Exception $e) {

                        $placeholder = trim((string)$this->dataHelper->getPlaceHolderImage());

                        if (!empty($placeholder)) {
                            $item['thumbnail'] = $placeholder;
                        } else {
                            try {
                                $dummyProduct = new DataObject($item);

                                $item['thumbnail'] = $this->imageHelper
                                    ->init($dummyProduct, 'product_listing_thumbnail')
                                    ->getUrl();
                            } catch (\Exception $e) {
                                // Keep existing thumbnail
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get Bynder Thumbnail
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return string|null
     */
    private function getThumbnail($product): ?string
    {
        $json = $product->getData('bynder_multi_img');

        if (empty($json)) {
            return null;
        }

        $images = json_decode($json, true);

        if (!is_array($images)) {
            return null;
        }

        foreach ($images as $skuImages) {

            if (!is_array($skuImages)) {
                continue;
            }

            foreach ($skuImages as $image) {

                if (
                    !empty($image['thum_url']) &&
                    isset($image['image_role']) &&
                    is_array($image['image_role']) &&
                    in_array('Thumbnail', $image['image_role'])
                ) {
                    return trim($image['thum_url']);
                }
            }
        }

        return null;
    }
}