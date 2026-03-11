<?php

namespace DamConsultants\Ahfproducts\Plugin\Catalog\Block\Product;

use Magento\Catalog\Block\Product\ImageFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\View\Asset\Repository as AssetRepository;

class ImageFactoryPlugin
{
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var AssetRepository
     */
    private $assetRepository;

    /**
     * Constructor
     *
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param AssetRepository $assetRepository
     */
    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        AssetRepository $assetRepository
    ) {
        $this->productRepository = $productRepository;
        $this->assetRepository = $assetRepository;
    }

    /**
     * Plugin for ImageFactory::create()
     *
     * @param ImageFactory $subject
     * @param \Closure $proceed
     * @param Product $product
     * @param string $imageId
     * @param array|null $attributes
     * @return \Magento\Catalog\Block\Product\Image
     * @throws NoSuchEntityException
     */
    public function aroundCreate(
        ImageFactory $subject,
        \Closure $proceed,
        Product $product,
        string $imageId,
        array $attributes = null
    ) {
        $productDetails = $this->productRepository->getById($product->getId());
        $useBynderCdn = $productDetails->getData('use_bynder_cdn');
        $bynderImages = $productDetails->getData('bynder_multi_img');

        if ($useBynderCdn && !empty($bynderImages)) {
            $jsonValue = json_decode($bynderImages, true);
            $imageUrl = null;
            if (is_array($jsonValue)) {
                foreach ($jsonValue as $values) {
                    if (isset($values['image_role']) && in_array('Small', $values['image_role'])) {
                        $imageUrl = trim($values['thum_url']);
                        break;
                    }
                }
            }

            if ($imageUrl) {
                $attributes['src'] = $imageUrl;
            }
        }

        return $proceed($product, $imageId, $attributes);
    }
}
