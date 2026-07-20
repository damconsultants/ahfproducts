<?php
namespace DamConsultants\Ahfproducts\Plugin\Catalog\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Api\ProductRepositoryInterface;
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

    public function __construct(
        ProductRepositoryInterface $productRepository,
        Data $dataHelper
    ) {
        $this->productRepository = $productRepository;
        $this->dataHelper = $dataHelper;
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

            foreach (['links'] as $key) {

                if (!isset($productData[$key])) {
                    continue;
                }

                foreach (['related', 'upsell', 'crosssell'] as $type) {

                    if (empty($productData[$key][$type])) {
                        continue;
                    }

                    foreach ($productData[$key][$type] as &$item) {

                        try {
                            $product = $this->productRepository->getById($item['id']);

                            $image = $this->getThumbnail($product);

                            if (!$image) {
                                $image = $this->dataHelper->getPlaceHolderImage();
                            }

                            $item['thumbnail'] = $image;

                        } catch (\Exception $e) {
                            $item['thumbnail'] = $this->dataHelper->getPlaceHolderImage();
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get Bynder thumbnail
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return string|null
     */
    private function getThumbnail($product)
    {
        $json = $product->getData('bynder_multi_img');

        if (!$json) {
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
                    isset($image['image_role']) &&
                    is_array($image['image_role']) &&
                    in_array('Thumbnail', $image['image_role']) &&
                    !empty($image['thum_url'])
                ) {
                    return $image['thum_url'];
                }
            }
        }

        return null;
    }
}