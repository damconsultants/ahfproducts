<?php

namespace DamConsultants\Ahfproducts\Plugin\Product\View\Type;

use Magento\Framework\Json\DecoderInterface;
use Magento\Framework\Json\EncoderInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ConfigurableBlock;

class Configurable
{
    protected $jsonEncoder;
    protected $jsonDecoder;
    protected $productHelper;
    protected $helper;
	protected $bynderhelper;

    public function __construct(
        DecoderInterface $jsonDecoder,
        EncoderInterface $jsonEncoder,
        ProductHelper $productHelper,
        \Magento\ConfigurableProduct\Helper\Data $helper,
		\DamConsultants\Ahfproducts\Helper\Data $bynderhelper
    ) {
        $this->jsonEncoder = $jsonEncoder;
        $this->jsonDecoder = $jsonDecoder;
        $this->helper = $helper;
        $this->productHelper = $productHelper;
		$this->bynderhelper = $bynderhelper;
    }

    public function afterGetJsonConfig(ConfigurableBlock $subject, $result)
    {
        $result = $this->jsonDecoder->decode($result);
        $result['images'] = $this->getOptionImages($subject);
		$result['enable'] = $this->bynderhelper->byndeimageconfig();
        return $this->jsonEncoder->encode($result);
    }

    protected function getOptionImages(ConfigurableBlock $subject)
    {
        $images = [];
        foreach ($subject->getAllowProducts() as $product) {
            // Get Bynder images from custom attribute
            $bynderImages = $product->getData('bynder_multi_img');
            $use_bynder_both_image = $product->getUseBynderBothImage();
            $use_bynder_cdn = $product->getUseBynderCdn();
            if ($use_bynder_both_image == 1) {
                $bynderImageData = [];
                if ($bynderImages) {
                    $decodedBynderImages = json_decode($bynderImages, true);
                    $role_image = false;
                    if (is_array($decodedBynderImages)) {
                        foreach ($decodedBynderImages as $key => $bynderImage) {
                            if ($bynderImage['item_type'] == 'IMAGE' && isset($bynderImage['image_role'])) {
                                foreach ($bynderImage['image_role'] as $image_role) {
                                    if ($image_role == 'Base') {
                                        $role_image = true;
                                    }
                                }
                            }
                            $bynderImageData[] = [
                                'thumb' => $bynderImage['thum_url'] ?? '',
                                'img' => $bynderImage['item_url'] ?? '',
                                'full' => $bynderImage['item_url'] ?? '',
                                'caption' => $bynderImage['alt_text'] ?? '',
                                'position' => $key + 1,
                                'isMain' => $role_image,
                                'type' => ($bynderImage['item_type'] == 'IMAGE') ? 'image' : 'video',
                                'videoUrl' => ($bynderImage['item_type'] == 'VIDEO') ? $bynderImage['item_url'] : null,
                                'src' => ($bynderImage['item_type'] == 'VIDEO') ? $bynderImage['item_url'] : null,
                            ];
                        }
                    }
                }

                // Get product gallery images using the injected product helper
                $productImages = $this->helper->getGalleryImages($product) ?: [];
                $galleryImages = [];
                foreach ($productImages as $image) {
                    $galleryImages[] = [
                        'thumb' => $image->getData('small_image_url'),
                        'img' => $image->getData('medium_image_url'),
                        'full' => $image->getData('large_image_url'),
                        'caption' => $image->getLabel(),
                        'position' => $image->getPosition(),
                        'isMain' => $image->getFile() == $product->getImage(),
                        'type' => $image->getMediaType() ? str_replace('external-', '', $image->getMediaType()) : '',
                        'videoUrl' => $image->getVideoUrl(),
                    ];
                }

                // Merge Bynder images and gallery images
                $images[$product->getId()] = array_merge($galleryImages, $bynderImageData);
            } elseif ($use_bynder_cdn == 1) {
                $bynderImageData = [];
                $galleryImages = [];
                if ($bynderImages) {
                    $decodedBynderImages = json_decode($bynderImages, true);
                    $role_image = false;
                    if (is_array($decodedBynderImages)) {
                        foreach ($decodedBynderImages as $key => $bynderImage) {
                            if ($bynderImage['item_type'] == 'IMAGE' && isset($bynderImage['image_role'])) {
                                foreach ($bynderImage['image_role'] as $image_role) {
                                    if ($image_role == 'Base') {
                                        $role_image = true;
                                    }
                                }
                            }
                            $bynderImageData[] = [
                                'thumb' => $bynderImage['thum_url'] ?? '',
                                'img' => $bynderImage['item_url'] ?? '',
                                'full' => $bynderImage['item_url'] ?? '',
                                'caption' => $bynderImage['alt_text'] ?? '',
                                'position' => $key + 1,
                                'isMain' => $role_image,
                                'type' => ($bynderImage['item_type'] == 'IMAGE') ? 'image' : 'video',
                                'videoUrl' => ($bynderImage['item_type'] == 'VIDEO') ? $bynderImage['item_url'] : null,
                                'src' => ($bynderImage['item_type'] == 'VIDEO') ? $bynderImage['item_url'] : null,
                            ];
                        }
                    }
                }
                $images[$product->getId()] = array_merge($galleryImages, $bynderImageData);
            } else {
                $productImages = $this->helper->getGalleryImages($product) ?: [];
                $galleryImages = [];
                $bynderImageData = [];
                foreach ($productImages as $image) {
                    $galleryImages[] = [
                        'thumb' => $image->getData('small_image_url'),
                        'img' => $image->getData('medium_image_url'),
                        'full' => $image->getData('large_image_url'),
                        'caption' => $image->getLabel(),
                        'position' => $image->getPosition(),
                        'isMain' => $image->getFile() == $product->getImage(),
                        'type' => $image->getMediaType() ? str_replace('external-', '', $image->getMediaType()) : '',
                        'videoUrl' => $image->getVideoUrl(),
                    ];
                }

                // Merge Bynder images and gallery images
                $images[$product->getId()] = array_merge($galleryImages, $bynderImageData);
            }


        }
        return $images;
    }
}
